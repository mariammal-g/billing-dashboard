<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\ResetPasswordMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="Authentication endpoints"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="User login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="remember_me", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="expires_in", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=401),
     *             @OA\Property(property="message", type="string", example="Invalid credentials"),
     *             @OA\Property(property="details", type="object",
     *                 @OA\Property(property="attempts_remaining", type="integer", example=4),
     *                 @OA\Property(property="attempts_made", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many login attempts",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=429),
     *             @OA\Property(property="message", type="string", example="Too many login attempts. Account temporarily locked."),
     *             @OA\Property(property="details", type="object",
     *                 @OA\Property(property="attempts_made", type="integer", example=5),
     *                 @OA\Property(property="blocked_until", type="string", format="date-time"),
     *                 @OA\Property(property="remaining_time", type="integer", example=1440),
     *                 @OA\Property(property="remaining_time_formatted", type="string", example="24h 0m"),
     *                 @OA\Property(property="suggestion", type="string", example="Please use the forgot password feature to reset your password.")
     *             )
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'remember_me' => 'boolean'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        $email = $validated['email'];
        
        // Find user and check if they can attempt login
        $user = User::where('email', $email)->first();
        
        if ($user && !$user->canAttemptLogin()) {
            $remainingTime = $user->last_attempt_at->copy()->addDay()->diffInMinutes(now());
            $hours = floor($remainingTime / 60);
            $minutes = $remainingTime % 60;
            
            return response()->json([
                'status' => 429,
                'message' => 'Too many login attempts. Account temporarily locked.',
                'details' => [
                    'attempts_made' => $user->attempts,
                    'blocked_until' => $user->last_attempt_at->copy()->addDay()->toISOString(),
                    'remaining_time' => $remainingTime,
                    'remaining_time_formatted' => $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
                    'suggestion' => 'Please use the forgot password feature to reset your password.'
                ]
            ], 429);
        }

        $credentials = $request->only('email', 'password');
        
        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                // Record failed attempt if user exists
                if ($user) {
                    $user->recordFailedAttempt();
                    $remaining = $user->getRemainingAttempts();
                    
                    if ($remaining <= 0) {
                        $remainingTime = $user->last_attempt_at->copy()->addDay()->diffInMinutes(now());
                        $hours = floor($remainingTime / 60);
                        $minutes = $remainingTime % 60;
                        
                        return response()->json([
                            'status' => 429,
                            'message' => 'Too many login attempts. Account temporarily locked.',
                            'details' => [
                                'attempts_made' => $user->attempts,
                                'blocked_until' => $user->last_attempt_at->copy()->addDay()->toISOString(),
                                'remaining_time' => $remainingTime,
                                'remaining_time_formatted' => $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m",
                                'suggestion' => 'Please use the forgot password feature to reset your password.'
                            ]
                        ], 429);
                    }
                    
                    return response()->json([
                        'status' => 401,
                        'message' => 'Invalid credentials',
                        'details' => [
                            'attempts_remaining' => $remaining,
                            'attempts_made' => $user->attempts
                        ]
                    ], 401);
                }
                
                return response()->json([
                    'status' => 401,
                    'message' => 'Invalid credentials'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Could not create token'
            ], 500);
        }

        // Reset login attempts on successful login
        $user = Auth::user();
        $user->resetLoginAttempts();
        
        // Set token expiry based on remember_me
        $expiryMinutes = $validated['remember_me'] ?? false ? 20160 : 480; // 7 days or 8 hours
        
        return response()->json([
            'status' => 200,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('roles'),
                'token' => $token,
                'expires_in' => $expiryMinutes * 60
            ]
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="Get current user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="User data retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=401),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function me()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'status' => 401,
                    'message' => 'User not found'
                ], 401);
            }

            return response()->json([
                'status' => 200,
                'message' => 'User data retrieved successfully',
                'data' => $user->load('roles')
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 401,
                'message' => 'Token is invalid'
            ], 401);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/forgot-password",
     *     summary="Send password reset email",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset email sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Password reset email sent successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=404),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();
        
        if (!$user) {
            return response()->json([
                'status' => 404,
                'message' => 'User not found'
            ], 404);
        }

        // Reset login attempts when user requests password reset
        $user->resetLoginAttempts();

        $token = $this->generateToken($user, 60); // 60 minutes expiry

        // Send email
        Mail::to($user->email)->send(new ResetPasswordMail($user, $token));

        return response()->json([
            'status' => 200,
            'message' => 'Password reset email sent successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/verify-token",
     *     summary="Verify password reset token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", example="reset_token_here")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token is valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Token is valid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=400),
     *             @OA\Property(property="message", type="string", example="Invalid or expired token")
     *         )
     *     )
     * )
     */
    public function verifyToken(Request $request)
    {
        try {
            $validated = $request->validate(['token' => 'required|string']);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        $reset = DB::table('password_reset_tokens')->where('token', $validated['token'])->first();
        if (!$reset || Carbon::now()->greaterThan($reset->expires_at)) {
            return response()->json(['status' => 400, 'message' => 'Invalid or expired token'], 400);
        }

        return response()->json(['status' => 200, 'message' => 'Token is valid'], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/setup-password",
     *     summary="Set new password using reset token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","password","password_confirmation"},
     *             @OA\Property(property="token", type="string", example="reset_token_here"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password set successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Password set successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=400),
     *             @OA\Property(property="message", type="string", example="Invalid or expired token")
     *         )
     *     )
     * )
     */
    public function setupPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        $reset = DB::table('password_reset_tokens')->where('token', $validated['token'])->first();
        if (!$reset || Carbon::now()->greaterThan($reset->expires_at)) {
            return response()->json(['status' => 400, 'message' => 'Invalid or expired token'], 400);
        }

        $user = User::where('email', $reset->email)->first();
        if (!$user) {
            return response()->json(['status' => 400, 'message' => 'User not found'], 400);
        }

        DB::beginTransaction();
        try {
            $user->update([
                'password' => Hash::make($validated['password']),
                'email_verified_at' => Carbon::now(),
            ]);
            DB::table('password_reset_tokens')->where('token', $validated['token'])->delete();
            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Password set successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to set password'
            ], 500);
        }
    }

    /**
     * Generate a secure token for password reset
     */
    protected function generateToken(User $user, int $expiryMinutes): string
    {
        $timestamp = now()->getTimestamp();
        $randomString = Str::random(32);
        $token = hash('sha256', $user->id . $randomString . $timestamp);

        // Clean up expired tokens first
        User::cleanupExpiredTokens();

        // Delete any existing tokens for this email to prevent duplicate entry errors
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Insert the new token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => now(),
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        return $token;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout current user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=401),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'status' => 200,
                'message' => 'Logout successful'
            ], 200);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Could not logout, please try again'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/auth/update-password",
     *     summary="Update current user password",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="current_password", type="string", example="currentpassword"),
     *             @OA\Property(property="password", type="string", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=401),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=422),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="details", type="object")
     *         )
     *     )
     * )
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 400,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $user->update([
                'password' => Hash::make($validated['password']),
                'modified_by' => $user->id,
            ]);

            // Reset login attempts when password is changed
            $user->resetLoginAttempts();

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Password updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update password'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/update-email",
     *     summary="Update user email",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="newemail@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Email updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=401),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=422),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="details", type="object")
     *         )
     *     )
     * )
     */
    public function updateEmail(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email,' . $user->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user->update(['email' => $validated['email']]);
            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Email updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update email'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/update-name",
     *     summary="Update user name",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name"},
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Name updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Name updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=401),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=422),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="details", type="object")
     *         )
     *     )
     * )
     */
    public function updateName(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized'], 401);
        }

        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'details' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name']
            ]);
            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Name updated successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update name'
            ], 500);
        }
    }
}

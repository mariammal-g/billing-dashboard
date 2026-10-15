<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Trivent Middleware</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 5px 5px;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #2980b9;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to Trivent Middleware!</h1>
    </div>
    
    <div class="content">
        <h2>Hello {{ $user->first_name }} {{ $user->last_name }},</h2>
        
        <p>Welcome to Trivent Middleware! We're excited to have you on board.</p>
        
        <p>Your account has been created successfully. To get started, please set up your password by clicking the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $frontendUrl }}?token={{ $token }}" class="button">Set Up Password</a>
        </div>
        
        <p>This link will expire in 24 hours for security reasons.</p>
        
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        
        <p>Best regards,<br>
        The Trivent Middleware Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent to {{ $user->email }}. If you didn't expect this email, please ignore it.</p>
        <p>&copy; {{ date('Y') }} Trivent Middleware. All rights reserved.</p>
    </div>
</body>
</html>

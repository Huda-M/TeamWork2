<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fb;
            color: #333333;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: none;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f7fb;
            padding: 40px 0;
        }
        .main-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            padding: 35px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 40px;
        }
        .content h2 {
            color: #1e3a8a;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .content p {
            font-size: 16px;
            line-height: 1.6;
            color: #4b5563;
            margin: 0 0 20px 0;
        }
        .code-container {
            background-color: #eff6ff;
            border: 2px dashed #bfdbfe;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            margin: 30px 0;
        }
        .code {
            font-size: 36px;
            font-weight: bold;
            color: #1d4ed8;
            letter-spacing: 6px;
            margin: 0;
        }
        .info {
            background-color: #f8fafc;
            border-left: 4px solid #94a3b8;
            padding: 15px 20px;
            border-radius: 0 4px 4px 0;
            margin: 25px 0 20px 0;
        }
        .info p {
            color: #475569;
            margin: 0;
            font-size: 14px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 30px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            color: #64748b;
            font-size: 14px;
            margin: 0;
            line-height: 1.5;
        }
        .team {
            font-weight: 700;
            color: #1e3a8a;
        }
        @media screen and (max-width: 600px) {
            .content { padding: 30px 20px; }
            .header { padding: 30px 20px; }
            .footer { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-container">
            <div class="header">
                <h1>Email Verification</h1>
            </div>
            
            <div class="content">
                <h2>Welcome to BridgeX!</h2>
                
                <p>Thank you for signing up. To complete your registration and verify your email address, please use the following verification code:</p>
                
                <div class="code-container">
                    <div class="code">{{ $code }}</div>
                </div>
                
                <p><strong>Important:</strong> This code will expire in <strong>10 minutes</strong>.</p>
                
                <div class="info">
                    <p>If you didn't request this email or create an account with us, please ignore this message.</p>
                </div>
                
                <p style="margin-bottom: 0;">Best regards,<br>
                <span class="team">BridgeX Team</span></p>
            </div>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} BridgeX. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>

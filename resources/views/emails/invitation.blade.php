<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitation</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', 'Poppins', Arial, sans-serif;
            background-color: #f4f7fc;
        }
        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #5c7dc9 0%, #274c9e 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .email-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .email-body {
            padding: 40px 30px;
            color: #333;
        }
        .team-name {
            font-size: 24px;
            font-weight: 700;
            color: #274c9e;
            margin: 15px 0 5px;
        }
        .project-title {
            font-size: 18px;
            color: #5c7dc9;
            margin-bottom: 25px;
            border-left: 4px solid #5c7dc9;
            padding-left: 15px;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #5c7dc9, #274c9e);
            color: white !important;
            text-decoration: none;
            padding: 14px 30px;
            border-radius: 50px;
            font-weight: 600;
            margin: 20px 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: 0.3s;
        }
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .footer {
            background-color: #f0f4f8;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e0e7ed;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>✨ Team Invitation ✨</h1>
            <p>You've been invited to join a team on TeamWork Platform</p>
        </div>
        <div class="email-body">
            <p style="font-size: 18px;">Hello <strong>{{ $notifiable->name }}</strong>,</p>
            <p>You have been invited to join the team:</p>
            <div class="team-name">{{ $team->name }}</div>
            <div class="project-title">📌 Project: {{ $team->project->title }}</div>
            <p style="margin-top: 25px;">Click the button below to view your invitation and accept it:</p>
            <div style="text-align: center;">
                <a href="{{ $url }}" class="button">🔗 View Invitation</a>
            </div>
            <p style="margin-top: 30px;">If you don't have an account yet, you will be guided to create one.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} TeamWork Platform. All rights reserved.<br>
            If you didn't expect this invitation, you can safely ignore this email.
        </div>
    </div>
</body>
</html>

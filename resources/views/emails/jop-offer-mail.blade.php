<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Offer from {{ $jopOffer->company_name }} - BridgeX</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f4f8;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #1d4ed8;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #1e293b;
        }
        .offer-title {
            color: #1d4ed8;
            font-size: 24px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .details-box {
            background-color: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 4px 4px 0;
        }
        .detail-item {
            margin-bottom: 10px;
            font-size: 15px;
        }
        .detail-item strong {
            color: #475569;
            display: inline-block;
            width: 120px;
        }
        .description {
            line-height: 1.6;
            margin-bottom: 30px;
            color: #475569;
        }
        .contact-info {
            background-color: #eff6ff;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
            text-align: center;
        }
        .contact-info h3 {
            margin-top: 0;
            color: #1d4ed8;
            font-size: 18px;
        }
        .contact-info a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .footer {
            background-color: #f1f5f9;
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }
        .footer strong {
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BridgeX</h1>
            <p>New Job Offer Received!</p>
        </div>
        
        <div class="content">
            <div class="greeting">Hello Developer,</div>
            
            <p>You have received a new job offer from <strong>{{ $jopOffer->company_name }}</strong> via BridgeX. Here are the details of the opportunity:</p>
            
            <h2 class="offer-title">{{ $jopOffer->title }}</h2>
            
            <div class="details-box">
                <div class="detail-item">
                    <strong>Salary Range:</strong> {{ $jopOffer->salary_range }}
                </div>
                <div class="detail-item">
                    <strong>Job Type:</strong> {{ ucfirst(str_replace('-', ' ', $jopOffer->job_type)) }}
                </div>
                <div class="detail-item">
                    <strong>Work Type:</strong> {{ ucfirst(str_replace('-', ' ', $jopOffer->work_type)) }}
                </div>
            </div>
            
            <h3>Job Description</h3>
            <div class="description">
                {!! nl2br(e($jopOffer->description)) !!}
            </div>
            
            <div class="contact-info">
                <h3>Interested?</h3>
                <p>Reply directly to the company via their contact email:</p>
                <p><a href="mailto:{{ $jopOffer->company->user->email }}">{{ $jopOffer->company->user->email }}</a></p>
            </div>
        </div>
        
        <div class="footer">
            <p>This email was sent via <strong>BridgeX</strong> platform.</p>
            <p>&copy; {{ date('Y') }} BridgeX. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
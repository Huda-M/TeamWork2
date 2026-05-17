<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Offer from {{ $jopOffer->company_name }} - BridgeX</title>
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
        .header p {
            color: #e0e7ff;
            margin: 10px 0 0 0;
            font-size: 16px;
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
        .content h3 {
            color: #1e3a8a;
            font-size: 18px;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        .content p {
            font-size: 16px;
            line-height: 1.6;
            color: #4b5563;
            margin: 0 0 20px 0;
        }
        .offer-title {
            color: #1e3a8a;
            font-size: 24px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .details-box {
            background-color: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 0 4px 4px 0;
        }
        .detail-item {
            margin-bottom: 10px;
            font-size: 15px;
            color: #4b5563;
        }
        .detail-item strong {
            color: #334155;
            display: inline-block;
            width: 120px;
        }
        .description {
            line-height: 1.6;
            margin-bottom: 30px;
            color: #4b5563;
            font-size: 16px;
        }
        .contact-info {
            background-color: #eff6ff;
            padding: 25px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        .contact-info h3 {
            margin-top: 0;
            color: #1e3a8a;
            font-size: 18px;
        }
        .contact-info a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
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
        .footer strong {
            color: #1e3a8a;
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
                <h1>BridgeX</h1>
                <p>New Job Offer Received!</p>
            </div>
            
            <div class="content">
                <h2>Hello Developer,</h2>
                
                <p>You have received a new job offer from <strong>{{ $jopOffer->company_name }}</strong> via BridgeX. Here are the details of the opportunity:</p>
                
                <div class="offer-title">{{ $jopOffer->title }}</div>
                
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
                    <p style="margin-bottom: 10px;">Reply directly to the company via their contact email:</p>
                    <p style="margin-bottom: 0;"><a href="mailto:{{ $companyEmail }}">{{ $companyEmail }}</a></p>
                </div>
            </div>
            
            <div class="footer">
                <p>This email was sent via <strong>BridgeX</strong> platform.</p>
                <p>&copy; {{ date('Y') }} BridgeX. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the Workspace</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #F8FAFC;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0F172A;
            line-height: 1.6;
            -webkit-text-size-adjust: 100%;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #F8FAFC;
            padding: 40px 0;
        }
        .main-card {
            max-width: 580px;
            margin: 0 auto;
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            padding: 36px 32px;
            text-align: center;
        }
        .logo-box {
            display: inline-block;
            background: #4F46E5;
            color: #FFFFFF;
            padding: 10px 18px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 20px;
            letter-spacing: -0.5px;
            margin-bottom: 12px;
        }
        .header h1 {
            color: #FFFFFF;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }
        .header p {
            color: #94A3B8;
            font-size: 14px;
            margin: 8px 0 0 0;
        }
        .content {
            padding: 36px 32px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #0F172A;
            margin-bottom: 12px;
        }
        .intro-text {
            color: #475569;
            font-size: 15px;
            margin-bottom: 24px;
        }
        .credentials-box {
            background: #F1F5F9;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .credentials-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748B;
            margin-bottom: 14px;
        }
        .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #CBD5E1;
            font-size: 14px;
        }
        .cred-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .cred-label {
            color: #64748B;
            font-weight: 500;
        }
        .cred-value {
            color: #0F172A;
            font-weight: 600;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .role-badge {
            background: #EEF2FF;
            color: #4F46E5;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0 24px 0;
        }
        .btn {
            display: inline-block;
            background: #4F46E5;
            color: #FFFFFF !important;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .note {
            font-size: 13px;
            color: #64748B;
            background: #FEF3C7;
            border-left: 4px solid #F59E0B;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 28px;
        }
        .footer {
            padding: 24px 32px;
            background: #F8FAFC;
            border-top: 1px solid #F1F5F9;
            text-align: center;
            font-size: 12px;
            color: #94A3B8;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            {{-- Header --}}
            <div class="header">
                <h1>Welcome to the Team!</h1>
                <p>Your workspace account is ready and configured.</p>
            </div>

            {{-- Body Content --}}
            <div class="content">
                <div class="greeting">Hello {{ $user->name }},</div>
                <div class="intro-text">
                    You have been invited and onboarded by <strong>{{ $onboardedBy->name }}</strong> to collaborate with the team. Below are your account details and login credentials:
                </div>

                {{-- Account Details Card --}}
                <div class="credentials-box">
                    <div class="credentials-title">Your Account Credentials</div>
                    
                    <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 14px;">
                        <tr>
                            <td style="color: #64748B; font-weight: 500;">Email Address:</td>
                            <td align="right" style="color: #0F172A; font-weight: 600;">{{ $user->email }}</td>
                        </tr>
                        @if ($temporaryPassword)
                        <tr>
                            <td style="color: #64748B; font-weight: 500;">Temporary Password:</td>
                            <td align="right" style="color: #0F172A; font-weight: 600; font-family: monospace; background: #E2E8F0; padding: 3px 8px; border-radius: 6px;">{{ $temporaryPassword }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="color: #64748B; font-weight: 500;">Assigned Role:</td>
                            <td align="right">
                                <span class="role-badge">{{ str_replace(['_', '-'], ' ', ucwords(str_replace(['-', '_'], ' ', $roleName))) }}</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="note">
                    <strong>Tip:</strong> For your security, we recommend changing your password from your account profile settings after your initial login.
                </div>

                {{-- CTA Button --}}
                <div class="btn-container">
                    <a href="{{ $loginUrl }}" class="btn" target="_blank">Access Your Dashboard &rarr;</a>
                </div>
            </div>

            {{-- Footer --}}
            <div class="footer">
                &copy; {{ date('Y') }} All rights reserved.<br>
                If you did not expect this invitation, please contact your workspace administrator.
            </div>
        </div>
    </div>
</body>
</html>

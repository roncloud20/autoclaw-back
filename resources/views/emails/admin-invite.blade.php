<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Invitation</title>
    <style>
        #accept-invite {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            color: #fff;
            background-color: #007BFF;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>Admin Invitation</h1>
    <p>{{ $inviter->firstname }} {{ $inviter->surname }} has invited you to join Autoclaw as an admin.</p>
    <a href="{{ $url }}" id="accept-invite">Accept Invitation</a>
    <p>This link will expire in {{ $hours }} hours and can only be used once.</p>
    <p>or copy and paste the following link into your browser:</p>
    <p>{{ $url }}</p>
    <p>If you weren't expecting this invitation, you can safely ignore this email.</p>
    <h6>Best regards,<br>The Team</h6>
</body>
</html>
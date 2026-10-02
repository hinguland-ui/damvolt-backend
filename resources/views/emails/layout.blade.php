<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#ffffff;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preheader')</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;">
<tr><td align="center" style="padding:24px 12px;">

    <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#333333;">

        {{-- Logo on a plain white header --}}
        <tr>
            <td style="padding:8px 0 22px;border-bottom:1px solid #e6e6e6;" align="left">
                @if (! empty($site['logoUrl']))
                    <img src="{{ $site['logoUrl'] }}" alt="{{ $site['name'] }}" height="40" style="display:block;height:40px;width:auto;border:0;">
                @elseif (! empty($site['logoPath']))
                    <img src="{{ $message->embed($site['logoPath']) }}" alt="{{ $site['name'] }}" height="40" style="display:block;height:40px;width:auto;border:0;">
                @else
                    <span style="font-size:20px;font-weight:700;color:#111111;">{{ $site['short'] }}</span>
                @endif
            </td>
        </tr>

        {{-- Body --}}
        <tr>
            <td style="padding:28px 0 8px;">
                <h1 style="margin:0 0 8px;font-size:20px;line-height:1.35;color:#111111;font-weight:600;">@yield('heading')</h1>
                <p style="margin:0 0 22px;font-size:14px;line-height:1.65;color:#666666;">@yield('lead')</p>
                @yield('content')
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td style="padding:24px 0 0;border-top:1px solid #e6e6e6;font-size:12px;line-height:1.7;color:#888888;">
                <strong style="color:#444444;">{{ $site['name'] }}</strong><br>
                @if ($site['address']) {{ $site['address'] }}<br> @endif
                @if ($site['phones']) {{ implode('  ·  ', $site['phones']) }}<br> @endif
                @if ($site['emails']) {{ implode('  ·  ', $site['emails']) }}<br> @endif
                @if ($site['hours']) {{ $site['hours'] }}<br> @endif
                @if ($site['gst']) GSTIN: {{ $site['gst'] }} @endif
            </td>
        </tr>
    </table>

</td></tr>
</table>
</body>
</html>

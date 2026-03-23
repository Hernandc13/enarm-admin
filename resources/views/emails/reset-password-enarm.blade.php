<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablece tu contraseña - ENARM CCM</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#24324a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f7fb; margin:0; padding:0;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px; background-color:#ffffff; border-radius:22px; overflow:hidden; box-shadow:0 12px 30px rgba(1,46,130,0.10);">
                    
                    <tr>
                        <td align="center" style="padding:30px 24px 20px 24px; background:linear-gradient(180deg, #ffffff 0%, #f7faff 100%); border-bottom:1px solid #e6edf7;">
                            <img src="{{ $logoUrl }}" alt="ENARM CCM" style="width:92px; height:auto; display:block; margin:0 auto 14px auto;">
                            <div style="font-size:30px; line-height:1.1; font-weight:800; color:#012e82; margin-bottom:6px;">
                                ENARM CCM
                            </div>
                            <div style="font-size:14px; color:#64748b; font-weight:600;">
                                Recuperación de contraseña
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:34px 32px 26px 32px;">
                            <div style="font-size:28px; line-height:1.2; font-weight:800; color:#012e82; margin-bottom:18px;">
                                Hola {{ $name }}
                            </div>

                            <div style="font-size:17px; line-height:1.7; color:#334155; margin-bottom:14px;">
                                Recibimos una solicitud para restablecer tu contraseña.
                            </div>

                            <div style="font-size:17px; line-height:1.7; color:#334155; margin-bottom:28px;">
                                Haz clic en el siguiente botón para crear una nueva contraseña:
                            </div>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 30px auto;">
                                <tr>
                                    <td align="center" bgcolor="#990017" style="border-radius:14px;">
                                        <a href="{{ $resetUrl }}"
                                           style="display:inline-block; padding:16px 28px; font-size:16px; font-weight:800; color:#ffffff; text-decoration:none; border-radius:14px; background-color:#990017;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="font-size:16px; line-height:1.7; color:#334155; margin-bottom:14px;">
                                Este enlace expirará en <strong>{{ $expiresInMinutes }} minutos</strong>.
                            </div>

                            <div style="font-size:16px; line-height:1.7; color:#334155; margin-bottom:24px;">
                                Si no solicitaste este cambio, puedes ignorar este correo.
                            </div>

                            <div style="height:1px; background-color:#e5edf7; margin:18px 0 22px 0;"></div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 24px; background-color:#f8fbff; border-top:1px solid #e6edf7; text-align:center;">
                            <div style="font-size:13px; color:#64748b; line-height:1.6;">
                                © {{ date('Y') }} ENARM CCM. Todos los derechos reservados.
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contraseña actualizada - ENARM CCM</title>
    <style>
        :root{
            --enarm-red: #990017;
            --enarm-blue: #012e82;
            --enarm-bg: #f4f7fb;
            --enarm-text: #334155;
            --enarm-muted: #64748b;
            --enarm-card: #ffffff;
            --enarm-border: #e6edf7;
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            min-height:100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(153,0,23,.08) 0, rgba(153,0,23,.08) 120px, transparent 121px),
                radial-gradient(circle at bottom left, rgba(1,46,130,.08) 0, rgba(1,46,130,.08) 150px, transparent 151px),
                var(--enarm-bg);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }

        .card{
            width:100%;
            max-width:460px;
            background: var(--enarm-card);
            border-radius:24px;
            padding:34px 28px 30px;
            text-align:center;
            box-shadow:0 14px 36px rgba(1,46,130,.10);
            border:1px solid var(--enarm-border);
        }

        .logo{
            width:92px;
            height:auto;
            display:block;
            margin:0 auto 14px auto;
        }

        .brand{
            color: var(--enarm-blue);
            font-size:30px;
            font-weight:800;
            line-height:1.1;
            margin-bottom:6px;
        }

        .subtitle{
            color: var(--enarm-muted);
            font-size:14px;
            font-weight:600;
            margin-bottom:24px;
        }

        .success-icon{
            width:72px;
            height:72px;
            margin:0 auto 20px auto;
            border-radius:50%;
            background: rgba(1,46,130,.08);
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .success-icon svg{
            width:34px;
            height:34px;
            color: var(--enarm-red);
        }

        .title{
            color: var(--enarm-blue);
            font-size:26px;
            font-weight:800;
            margin:0 0 12px;
            line-height:1.2;
        }

        .text{
            color: var(--enarm-text);
            font-size:16px;
            line-height:1.7;
            margin:0;
        }

        .note{
            margin-top:18px;
            color: var(--enarm-muted);
            font-size:14px;
            line-height:1.6;
        }

        .divider{
            height:1px;
            background: var(--enarm-border);
            margin:24px 0 0;
        }

        .footer{
            margin-top:16px;
            color: var(--enarm-muted);
            font-size:13px;
            line-height:1.6;
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ url('images/logo-enarm.png') }}" alt="ENARM CCM" class="logo">

        <div class="brand">ENARM CCM</div>
        <div class="subtitle">Recuperación de contraseña</div>

        <div class="success-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6L9 17l-5-5"></path>
            </svg>
        </div>

        <h1 class="title">Contraseña actualizada</h1>

        <p class="text">
            Tu contraseña se actualizó correctamente.
            Ya puedes volver a la app e iniciar sesión con tu nueva contraseña.
        </p>

        <div class="note">
            Puedes cerrar esta ventana y regresar a ENARM CCM.
        </div>

        <div class="divider"></div>

        <div class="footer">
            © {{ date('Y') }} ENARM CCM. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
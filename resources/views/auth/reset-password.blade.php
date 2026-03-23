<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - ENARM CCM</title>
    <style>
        :root{
            --enarm-red: #990017;
            --enarm-blue: #012e82;
            --enarm-bg: #f4f7fb;
            --enarm-text: #334155;
            --enarm-border: #d9e3f0;
            --enarm-card: #ffffff;
        }

        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            min-height:100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--enarm-bg);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }

        .wrap{
            width:100%;
            max-width:460px;
            background: var(--enarm-card);
            border-radius:24px;
            padding:30px 26px;
            box-shadow:0 14px 36px rgba(1,46,130,.10);
        }

        .brand{
            text-align:center;
            margin-bottom:22px;
        }

        .brand img{
            width:92px;
            height:auto;
            display:block;
            margin:0 auto 14px auto;
        }

        .brand h1{
            margin:0 0 6px;
            color: var(--enarm-blue);
            font-size:30px;
            line-height:1.1;
            font-weight:800;
        }

        .brand p{
            margin:0;
            color:#64748b;
            font-size:14px;
            font-weight:600;
        }

        .title{
            color: var(--enarm-blue);
            font-size:24px;
            font-weight:800;
            margin:0 0 18px;
            text-align:center;
        }

        label{
            display:block;
            margin:14px 0 6px;
            color: var(--enarm-blue);
            font-weight:700;
            font-size:14px;
        }

        input{
            width:100%;
            border:1px solid var(--enarm-border);
            border-radius:14px;
            padding:14px 14px;
            font-size:15px;
            color: var(--enarm-text);
            outline:none;
            background:#fff;
        }

        input:focus{
            border-color: var(--enarm-blue);
            box-shadow:0 0 0 3px rgba(1,46,130,.08);
        }

        .password-field{
            position:relative;
        }

        .password-field input{
            padding-right:52px;
        }

        .toggle-password{
            position:absolute;
            top:50%;
            right:12px;
            transform:translateY(-50%);
            border:none;
            background:transparent;
            color:#64748b;
            cursor:pointer;
            padding:6px;
            margin:0;
            width:auto;
            border-radius:10px;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .toggle-password:hover{
            background:rgba(1,46,130,.06);
            color:var(--enarm-blue);
        }

        .toggle-password:focus{
            outline:none;
            box-shadow:0 0 0 3px rgba(1,46,130,.10);
        }

        .toggle-password svg{
            width:20px;
            height:20px;
            display:block;
        }

        button[type="submit"]{
            width:100%;
            margin-top:20px;
            border:none;
            border-radius:14px;
            padding:15px 16px;
            background: var(--enarm-red);
            color:#fff;
            font-size:16px;
            font-weight:800;
            cursor:pointer;
        }

        .error-box{
            background:#fff1f2;
            border:1px solid #fecdd3;
            color:#b91c1c;
            border-radius:14px;
            padding:12px 14px;
            margin-bottom:14px;
            font-size:14px;
        }

        .error-box ul{
            margin:0;
            padding-left:18px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <img src="{{ url('images/logo-enarm.png') }}" alt="ENARM CCM">
            <h1>ENARM CCM</h1>
            <p>Recuperación de contraseña</p>
        </div>

        <div class="title">Restablecer contraseña</div>

        @if ($errors->any())
            <div class="error-box">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <label>Correo electrónico</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required>

            <label>Nueva contraseña</label>
            <div class="password-field">
                <input
                    type="password"
                    name="password"
                    id="password"
                    required
                >
                <button type="button" class="toggle-password" data-target="password" aria-label="Mostrar contraseña">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="icon-eye">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>

            <label>Confirmar contraseña</label>
            <div class="password-field">
                <input
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    required
                >
                <button type="button" class="toggle-password" data-target="password_confirmation" aria-label="Mostrar contraseña">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="icon-eye">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>

            <button type="submit">Guardar nueva contraseña</button>
        </form>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);

                if (!input) return;

                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';

                this.setAttribute(
                    'aria-label',
                    isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'
                );

                this.innerHTML = isPassword
                    ? `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.77 21.77 0 0 1 5.06-5.94"></path>
                            <path d="M9.9 4.24A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a21.8 21.8 0 0 1-3.17 4.36"></path>
                            <path d="M1 1l22 22"></path>
                            <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"></path>
                        </svg>
                    `
                    : `
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    `;
            });
        });
    </script>
</body>
</html>
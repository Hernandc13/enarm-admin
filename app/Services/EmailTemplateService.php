<?php

namespace App\Services;

use App\Models\Setting;

class EmailTemplateService
{
    public function render(string $type, array $payload, array $overrides = []): array
    {
        $k = $this->keys($type);
        $d = $this->defaults($type);

        $get = function (string $key, $default = '') use ($overrides) {
            return array_key_exists($key, $overrides) ? $overrides[$key] : Setting::get($key, $default);
        };

        $subject = (string) $get($k['subject'], $d['subject']);
        $bodyRaw = (string) $get($k['body'], $d['body']);

        // Logo
        $hardLogo    = 'https://i.imgur.com/WHdjsG4.png';
        $defaultLogo = trim((string) Setting::get('email_logo_url', $hardLogo));
        $logoUrl     = trim((string) ($payload['logo_url'] ?? ''));
        if ($logoUrl === '') {
            $logoUrl = $defaultLogo !== '' ? $defaultLogo : $hardLogo;
        }

        // URLs base
        $appUrl = trim((string) ($payload['app_url'] ?? config('app.url')));
        if ($appUrl === '') $appUrl = (string) config('app.url');

        $panelUrl  = trim((string) ($payload['panel_url'] ?? (rtrim($appUrl, '/') . '/admin_enarm')));
        $moodleUrl = trim((string) ($payload['moodle_url'] ?? (string) config('services.moodle.url')));

        // Texto base
        $name     = trim((string) ($payload['name'] ?? ''));
        $email    = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        // Variables
        $vars = [
            '{{name}}'      => e($name),
            '{{email}}'     => e($email),
            '{{password}}'  => e($password),
            '{{year}}'      => (string) now()->year,

            '{{app_url}}'   => $appUrl,
            '{{panel_url}}' => $panelUrl,
            '{{logo_url}}'  => $logoUrl,
            '{{moodle_url}}'=> $moodleUrl,
        ];

        // Layout config
        $brandTitleRaw    = (string) $get($k['brand_title'], $d['brand_title']);
        $brandSubtitleRaw = (string) $get($k['brand_subtitle'], $d['brand_subtitle']);
        $footerRaw        = (string) $get($k['footer_text'], $d['footer_text']);

        $headerColor = (string) $get($k['header_color'], $d['header_color']);
        $buttonColor = (string) $get($k['button_color'], $d['button_color']);

        // CTAs
        $ctas = [];
        if ($type === 'admin') {
            $adminBtnText = (string) $get($k['admin_cta_text'], $d['admin_cta_text']);
            $adminBtnUrl  = trim((string) $get($k['admin_cta_url'], $panelUrl));
            if ($adminBtnUrl === '') $adminBtnUrl = $panelUrl;

            $ctas[] = ['text' => strtr($adminBtnText, $vars), 'url' => $adminBtnUrl, 'color' => $buttonColor];
        } else {
            $iosText = (string) $get($k['ios_text'], $d['ios_text']);
            $iosUrl  = trim((string) $get($k['ios_url'], $d['ios_url']));
            $andText = (string) $get($k['android_text'], $d['android_text']);
            $andUrl  = trim((string) $get($k['android_url'], $d['android_url']));

            if ($iosUrl === '') $iosUrl = $appUrl;
            if ($andUrl === '') $andUrl = $appUrl;

            $ctas[] = ['text' => strtr($iosText, $vars), 'url' => $iosUrl, 'color' => $buttonColor];
            $ctas[] = ['text' => strtr($andText, $vars), 'url' => $andUrl, 'color' => $buttonColor];
        }

        // Password rules
        $showPassword = $type !== 'moodle';

        $bodyRendered       = strtr($bodyRaw, $vars);
        $brandTitleRendered = strtr($brandTitleRaw, $vars);
        $brandSubRendered   = strtr($brandSubtitleRaw, $vars);
        $footerRendered     = strtr($footerRaw, $vars);

        $rows = $this->accessRowsVertical([
            'email'        => $email,
            'password'     => $password,
            'showPassword' => $showPassword,
        ]);

        $html = $this->wrapEmail([
            'logo'         => $logoUrl,
            'hard_logo'    => $hardLogo,
            'brand_title'  => $brandTitleRendered,
            'subtitle'     => $brandSubRendered,
            'greeting'     => 'Hola ' . e($name) . ',',
            'body'         => $bodyRendered,
            'rows'         => $rows,
            'ctas'         => $ctas,
            'footer'       => $footerRendered,
            'header_color' => $headerColor,
        ]);

        return ['subject' => $subject, 'html' => $html];
    }

    private function keys(string $type): array
    {
        $prefix = match ($type) {
            'moodle' => 'moodle_email',
            'admin'  => 'admin_welcome_email',
            default  => 'welcome_email',
        };

        return [
            'subject'        => "{$prefix}_subject",
            'body'           => "{$prefix}_body",

            'brand_title'    => "{$prefix}_brand_title",
            'brand_subtitle' => "{$prefix}_brand_subtitle",
            'footer_text'    => "{$prefix}_footer_text",

            'header_color'   => "{$prefix}_header_color",
            'button_color'   => "{$prefix}_button_color",

            'ios_text'       => "{$prefix}_ios_text",
            'ios_url'        => "{$prefix}_ios_url",
            'android_text'   => "{$prefix}_android_text",
            'android_url'    => "{$prefix}_android_url",

            'admin_cta_text' => "{$prefix}_cta_text",
            'admin_cta_url'  => "{$prefix}_cta_url",
        ];
    }

    private function defaults(string $type): array
    {
        $common = [
            'brand_title'  => 'ENARM CCM',
            'footer_text'  => 'Si no solicitaste este acceso, ignora este mensaje o contacta al administrador.',
            'header_color' => '#990017',
            'button_color' => '#012e82',
        ];

        return match ($type) {
            'moodle' => $common + [
                'subject'        => 'Acceso a la app - Usa tus credenciales de Moodle',
                'brand_subtitle' => 'Acceso con credenciales Moodle',
                'body'           => '<p>Te damos la bienvenida. A continuación encontrarás tus accesos para iniciar sesión:</p>',
                'ios_text'       => 'Descarga iOS',
                'ios_url'        => '',
                'android_text'   => 'Descarga Android',
                'android_url'    => '',
                'admin_cta_text' => 'Abrir panel',
                'admin_cta_url'  => '',
            ],
            'admin' => $common + [
                'subject'        => 'Acceso de administrador - ENARM CCM',
                'brand_subtitle' => 'Acceso de administrador',
                'body'           => '<p>Tu acceso de administrador está listo. A continuación se muestran tus datos:</p>',
                'admin_cta_text' => 'Abrir panel',
                'admin_cta_url'  => '',
                'ios_text'       => 'Descarga iOS',
                'ios_url'        => '',
                'android_text'   => 'Descarga Android',
                'android_url'    => '',
            ],
            default => $common + [
                'subject'        => 'Bienvenido(a) - Accesos',
                'brand_subtitle' => 'Accesos a tu cuenta',
                'body'           => '<p>Te damos la bienvenida. A continuación encontrarás tus accesos para iniciar sesión:</p>',
                'ios_text'       => 'Descarga iOS',
                'ios_url'        => '',
                'android_text'   => 'Descarga Android',
                'android_url'    => '',
                'admin_cta_text' => 'Abrir panel',
                'admin_cta_url'  => '',
            ],
        };
    }

    private function accessRowsVertical(array $data): string
    {
        $email = e((string) ($data['email'] ?? ''));
        $pass  = e((string) ($data['password'] ?? ''));
        $showPassword = (bool) ($data['showPassword'] ?? true);

        $emailBlock = '
          <tr>
            <td style="padding:12px 14px; border-top:1px solid #e5e7eb;">
              <div style="font-size:12px; font-weight:700; color:#6b7280; margin-bottom:6px;">Correo</div>
              <div style="font-size:13px; color:#111827;">
                <a href="mailto:' . $email . '" style="color:#2563eb; text-decoration:none; font-weight:700;">' . $email . '</a>
              </div>
            </td>
          </tr>
        ';

        if (!$showPassword || trim((string) ($data['password'] ?? '')) === '') {
            return $emailBlock;
        }

        $passBlock = '
          <tr>
            <td style="padding:12px 14px; border-top:1px solid #e5e7eb;">
              <div style="font-size:12px; font-weight:700; color:#6b7280; margin-bottom:6px;">Contraseña</div>
              <div>
                <span style="display:inline-block; padding:6px 10px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size:13px; color:#111827; font-weight:700;">' . $pass . '</span>
              </div>
            </td>
          </tr>
        ';

        return $emailBlock . $passBlock;
    }

    private function wrapEmail(array $d): string
    {
        $logo        = (string) ($d['logo'] ?? '');
        $hardLogo    = (string) ($d['hard_logo'] ?? '');
        $brandTitle  = (string) ($d['brand_title'] ?? 'ENARM CCM');
        $subtitle    = (string) ($d['subtitle'] ?? '');
        $greeting    = (string) ($d['greeting'] ?? '');
        $body        = (string) ($d['body'] ?? '');
        $rows        = (string) ($d['rows'] ?? '');
        $ctas        = (array)  ($d['ctas'] ?? []);
        $footer      = (string) ($d['footer'] ?? '');
        $headerColor = (string) ($d['header_color'] ?? '#990017');

        $accessBox = '
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;">
            <tr>
              <td style="padding:12px 14px; background:#f9fafb; font-weight:800; font-size:13px; color:#111827;">Datos de acceso</td>
            </tr>
            ' . $rows . '
          </table>
        ';

        $buttonColor = $ctas[0]['color'] ?? '#012e82';

        $ctaHtml = '';
        if (count($ctas) === 1) {
            $c = $ctas[0];
            $ctaHtml = '
              <div style="text-align:center; padding:18px 0 8px 0;">
                <a href="' . $c['url'] . '" style="background:' . $buttonColor . '; color:#ffffff; text-decoration:none; font-weight:800; font-size:14px; padding:12px 18px; border-radius:12px; display:inline-block;">
                  ' . $c['text'] . '
                </a>
              </div>
            ';
        } else {
            $c1 = $ctas[0];
            $c2 = $ctas[1];
            $ctaHtml = '
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 8px 0;">
                <tr>
                  <td align="center">
                    <table role="presentation" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="padding:0 6px 8px 6px;" align="center">
                          <a href="' . $c1['url'] . '" style="background:' . $buttonColor . '; color:#ffffff; text-decoration:none; font-weight:800; font-size:14px; padding:12px 18px; border-radius:12px; display:inline-block;">
                            ' . $c1['text'] . '
                          </a>
                        </td>
                        <td style="padding:0 6px 8px 6px;" align="center">
                          <a href="' . $c2['url'] . '" style="background:' . $buttonColor . '; color:#ffffff; text-decoration:none; font-weight:800; font-size:14px; padding:12px 18px; border-radius:12px; display:inline-block;">
                            ' . $c2['text'] . '
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            ';
        }

        // ✅ onerror fallback para que no quede "Logo" roto.
        $img = '<img src="' . $logo . '" alt="Logo" width="62" height="62"
                  referrerpolicy="no-referrer"
                  loading="eager"
                  onerror="this.onerror=null;this.src=\'' . $hardLogo . '\';"
                  style="width:62px; height:62px; border-radius:999px; display:block; object-fit:cover;">';

        return '<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0; padding:0; background:#f3f4f6;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 0;">
    <tr>
      <td align="center" style="padding:0 16px;">
        <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%; max-width:640px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,.08);">
          <tr>
            <td style="background:' . $headerColor . '; padding:26px 24px; text-align:center;">
              <div style="display:inline-block; width:74px; height:74px; border-radius:999px; background:#ffffff; padding:6px; box-sizing:border-box;">
                ' . $img . '
              </div>
              <div style="color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-weight:800; letter-spacing:.3px; margin-top:12px; font-size:16px;">
                ' . $brandTitle . '
              </div>
              <div style="color:#ffffff; opacity:.9; font-family:Arial, Helvetica, sans-serif; margin-top:4px; font-size:13px;">
                ' . $subtitle . '
              </div>
            </td>
          </tr>

          <tr>
            <td style="padding:26px 26px 10px 26px; font-family:Arial, Helvetica, sans-serif; color:#111827;">
              <div style="font-size:24px; font-weight:800; text-align:center; margin-bottom:14px;">' . $greeting . '</div>

              <div style="font-size:14px; line-height:1.6; text-align:center; color:#374151; margin:0 auto 16px auto; max-width:520px;">
                ' . $body . '
              </div>

              ' . $accessBox . '

              ' . $ctaHtml . '

              <div style="text-align:center; font-size:12px; line-height:1.6; color:#6b7280; padding:8px 12px 0 12px;">
                ' . $footer . '
              </div>
            </td>
          </tr>

          <tr><td style="height:18px;"></td></tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}

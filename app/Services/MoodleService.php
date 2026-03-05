<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MoodleService
{
    private string $baseUrl;
    private string $token;
    private string $format;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.moodle.url'), '/');
        $this->token   = (string) config('services.moodle.wstoken');
        $this->format  = (string) config('services.moodle.format', 'json');
        $this->timeout = (int) config('services.moodle.timeout', 25);

        if ($this->baseUrl === '' || $this->token === '') {
            throw new \RuntimeException('Config Moodle incompleta: revisa MOODLE_URL y MOODLE_WSTOKEN en .env y config/services.php');
        }
    }

    private function endpoint(): string
    {
        return $this->baseUrl . '/webservice/rest/server.php';
    }

    public function getSiteInfo(): array
    {
        return $this->call('core_webservice_get_site_info');
    }

    /**
     * Lista usuarios desde Moodle (excluye suspendidos y eliminados).
     *
     * @return array<int, array{id:int, firstname:string, lastname:string, email:string}>
     */
    public function listUsers(): array
    {
        $data = $this->call('core_user_get_users', [
            'criteria' => [
                ['key' => 'email', 'value' => '%'], // <- CLAVE para tu Moodle
            ],
        ]);

        $users = $data['users'] ?? [];
        if (!is_array($users)) {
            $users = [];
        }

        $out = [];
        foreach ($users as $u) {
            $norm = $this->normalizeUser($u); // aquí ya filtramos suspended/deleted
            if ($norm !== null) {
                $out[] = $norm;
            }
        }

        return $out;
    }

    /**
     * Buscar usuarios por emails exactos (excluye suspendidos y eliminados).
     *
     * @return array<int, array{id:int, firstname:string, lastname:string, email:string}>
     */
    public function getUsersByEmail(array $emails): array
    {
        $emails = array_values(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), $emails)));
        if (empty($emails)) {
            return [];
        }

        // 1) primero obtenemos IDs por email (rápido)
        $byField = $this->call('core_user_get_users_by_field', [
            'field'  => 'email',
            'values' => $emails,
        ]);

        if (!is_array($byField) || empty($byField)) {
            return [];
        }

        $ids = [];
        foreach ($byField as $u) {
            if (is_array($u) && isset($u['id'])) {
                $id = (int) $u['id'];
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_values(array_unique($ids));
        if (empty($ids)) {
            return [];
        }

        // 2) ahora consultamos detalles por IDs para poder filtrar mejor
        $detailed = $this->call('core_user_get_users_by_id', [
            'userids' => $ids,
        ]);

        if (!is_array($detailed)) {
            return [];
        }

        $out = [];
        foreach ($detailed as $u) {
            $norm = $this->normalizeUser($u);
            if ($norm !== null) {
                $out[] = $norm;
            }
        }

        return $out;
    }

    /**
     * Llamada genérica WS.
     */
    private function call(string $wsfunction, array $params = []): array
    {
        $payload = array_merge([
            'wstoken'            => $this->token,
            'wsfunction'         => $wsfunction,
            'moodlewsrestformat' => $this->format,
        ], $this->flatten($params));

        $res = Http::asForm()
            ->timeout($this->timeout)
            ->post($this->endpoint(), $payload);

        if (!$res->ok()) {
            throw new \RuntimeException('HTTP ' . $res->status() . ': ' . $res->body());
        }

        $body = (string) $res->body();
        $json = json_decode($body, true);

        if (!is_array($json)) {
            throw new \RuntimeException('Respuesta inválida de Moodle (no se pudo decodificar JSON). Body: ' . $body);
        }

        if (isset($json['exception'])) {
            $exception = (string) ($json['exception'] ?? '');
            $errorcode = (string) ($json['errorcode'] ?? '');
            $message   = (string) ($json['message'] ?? 'Error desde Moodle WS');
            $debuginfo = (string) ($json['debuginfo'] ?? '');

            $extra = trim($errorcode . ' ' . $exception);
            if ($extra !== '') {
                $extra = " ({$extra})";
            }

            if ($debuginfo !== '') {
                $message .= " | Debug: {$debuginfo}";
            }

            throw new \RuntimeException($message . $extra);
        }

        return $json;
    }

    /**
     * Normaliza estructura de usuario de Moodle y filtra:
     * - deleted = 1 (eliminado)
     * - suspended = 1 (suspendido)
     *
     * OJO: si el WS no retorna esos campos, se asume 0 y NO filtra.
     *
     * @return array{id:int, firstname:string, lastname:string, email:string}|null
     */
    private function normalizeUser(mixed $u): ?array
    {
        if (!is_array($u)) {
            return null;
        }

        $id    = (int) ($u['id'] ?? 0);
        $email = strtolower(trim((string) ($u['email'] ?? '')));

        if ($id <= 0) {
            return null;
        }

        // Filtrado seguro (si vienen, se aplican; si no, no rompe)
        $suspended = isset($u['suspended']) ? (int) $u['suspended'] : 0;
        $deleted   = isset($u['deleted'])   ? (int) $u['deleted']   : 0;

        if ($deleted === 1 || $suspended === 1) {
            return null;
        }

        return [
            'id'        => $id,
            'firstname' => (string) ($u['firstname'] ?? ''),
            'lastname'  => (string) ($u['lastname'] ?? ''),
            'email'     => $email,
        ];
    }

    /**
     * Convierte arrays anidados a criteria[0][key].
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';

            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}

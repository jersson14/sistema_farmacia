<?php
require_once __DIR__ . "/../config/Conexion.php";
require_once __DIR__ . "/AIConfig.php";
require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Servicio central de IA: cliente OpenAI + envio de correos SMTP.
 *
 * No hace logica de negocio; solo expone primitivas para que los
 * reportes y el chat las usen.
 */
class AIService
{
    private $config;
    private $client;

    public function __construct()
    {
        $this->config = (new AIConfig())->obtener();
    }

    public function estaConfigurado()
    {
        return !empty($this->config['openai_api_key']);
    }

    private function client()
    {
        if ($this->client) return $this->client;
        if (empty($this->config['openai_api_key'])) {
            throw new RuntimeException("Falta configurar la OpenAI API key en Integraciones.");
        }
        $factory = OpenAI::factory()->withApiKey($this->config['openai_api_key']);
        if (!empty($this->config['openai_org'])) {
            $factory = $factory->withOrganization($this->config['openai_org']);
        }
        $this->client = $factory->make();
        return $this->client;
    }

    /**
     * Llamada basica de chat completions.
     * @param array $messages [{role, content}, ...]
     * @return array ['contenido' => string, 'tokens_prompt' => int, 'tokens_respuesta' => int]
     */
    public function chat(array $messages, array $opts = [])
    {
        $modelo = $opts['model'] ?? ($this->config['openai_model'] ?? 'gpt-4o-mini');
        $temperature = $opts['temperature'] ?? 0.3;

        $payload = [
            'model'       => $modelo,
            'messages'    => $messages,
            'temperature' => $temperature,
        ];
        if (isset($opts['max_tokens'])) $payload['max_tokens'] = $opts['max_tokens'];
        if (isset($opts['response_format'])) $payload['response_format'] = $opts['response_format'];

        $r = $this->client()->chat()->create($payload);

        return [
            'contenido'        => $r->choices[0]->message->content ?? '',
            'tokens_prompt'    => $r->usage->promptTokens    ?? 0,
            'tokens_respuesta' => $r->usage->completionTokens ?? 0,
        ];
    }

    /**
     * Envia un correo HTML via SMTP.
     * @param string|array $destinatarios "a@x.com" o ["a@x.com","b@x.com"] o "a@x.com,b@x.com"
     */
    public function enviarCorreo($destinatarios, $asunto, $html, $textoAlt = '')
    {
        $c = $this->config;
        if (empty($c['smtp_host']) || empty($c['smtp_from'])) {
            throw new RuntimeException("Falta configurar SMTP (host / remitente) en Integraciones.");
        }

        $lista = is_array($destinatarios)
            ? $destinatarios
            : array_filter(array_map('trim', explode(',', (string)$destinatarios)));
        if (empty($lista)) {
            throw new RuntimeException("No se especifico destinatario.");
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $c['smtp_host'];
        $mail->Port       = (int)$c['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        if (!empty($c['smtp_user'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $c['smtp_user'];
            $mail->Password = $c['smtp_pass'];
        }
        $secure = strtolower($c['smtp_secure'] ?? 'tls');
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        // Algunos hostings (Hostinger, cPanel) requieren estas opciones para el handshake SSL
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        // Hostinger y muchos SMTP exigen que el From coincida con el usuario autenticado.
        // Si smtp_from esta vacio, usar smtp_user como remitente.
        $fromAddr = !empty($c['smtp_from']) ? $c['smtp_from'] : $c['smtp_user'];
        $fromName = $c['smtp_from_name'] ?: $fromAddr;
        if (empty($fromAddr)) {
            throw new RuntimeException("Falta el correo remitente (From) o el usuario SMTP.");
        }
        $mail->setFrom($fromAddr, $fromName);
        foreach ($lista as $addr) $mail->addAddress($addr);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        $mail->AltBody = $textoAlt ?: strip_tags($html);

        $mail->send();
        return true;
    }
}

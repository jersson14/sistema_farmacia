<?php
require_once __DIR__ . "/../config/Conexion.php";

class Empresa
{
    public function __construct()
    {
    }

    public function obtener()
    {
        $sql = "SELECT * FROM configuracion_empresa ORDER BY idconfig ASC LIMIT 1";
        return ejecutarConsultaSimpleFila($sql);
    }

    public function guardar($data)
    {
        $exist = $this->obtener();

        $nombre_comercial = $data['nombre_comercial'];
        $razon_social = $data['razon_social'];
        $ruc = $data['ruc'];
        $direccion = $data['direccion'];
        $telefono = $data['telefono'];
        $celular = $data['celular'];
        $correo = $data['correo'];
        $web = $data['web'];
        $logo = $data['logo'];
        $color_primario = $data['color_primario'];
        $color_secundario = $data['color_secundario'];
        $serie_boleta = $data['serie_boleta'];
        $serie_factura = $data['serie_factura'];
        $serie_ticket = $data['serie_ticket'];
        $impuesto_default = $data['impuesto_default'];
        $moneda = $data['moneda'];
        $nombre_impresora = $data['nombre_impresora'] ?? '';

        if ($exist) {
            $sql = "UPDATE configuracion_empresa SET
                nombre_comercial='$nombre_comercial',
                razon_social='$razon_social',
                ruc='$ruc',
                direccion='$direccion',
                telefono='$telefono',
                celular='$celular',
                correo='$correo',
                web='$web',
                logo='$logo',
                color_primario='$color_primario',
                color_secundario='$color_secundario',
                serie_boleta='$serie_boleta',
                serie_factura='$serie_factura',
                serie_ticket='$serie_ticket',
                impuesto_default='$impuesto_default',
                moneda='$moneda',
                nombre_impresora='$nombre_impresora'
                WHERE idconfig='" . $exist['idconfig'] . "'";
            return ejecutarConsulta($sql);
        }

        $sql = "INSERT INTO configuracion_empresa (
            nombre_comercial,razon_social,ruc,direccion,telefono,celular,correo,web,logo,
            color_primario,color_secundario,serie_boleta,serie_factura,serie_ticket,impuesto_default,moneda
        ) VALUES (
            '$nombre_comercial','$razon_social','$ruc','$direccion','$telefono','$celular','$correo','$web','$logo',
            '$color_primario','$color_secundario','$serie_boleta','$serie_factura','$serie_ticket','$impuesto_default','$moneda'
        )";
        return ejecutarConsulta($sql);
    }

    /** Logo neutro que se usa solo si la empresa no subió el suyo (Acceso > Empresa). */
    const LOGO_DEFECTO = 'files/logo_default.png';
    /** Nombre que se muestra solo si la empresa no configuró nombre comercial ni razón social. */
    const NOMBRE_DEFECTO = 'Mi Farmacia';

    /**
     * Archivo del logo relativo a la raíz del proyecto:
     * el logo configurado en Empresa (files/empresa/...) o el logo neutro por defecto.
     */
    public function logoRelativo()
    {
        $cfg = $this->obtener();
        if (!empty($cfg['logo'])) {
            $fs = realpath(__DIR__ . '/../files/empresa/' . $cfg['logo']);
            if ($fs && is_file($fs)) {
                return 'files/empresa/' . $cfg['logo'];
            }
        }
        return self::LOGO_DEFECTO;
    }

    /** Ruta absoluta en disco del logo (para FPDF y tickets). */
    public function logoArchivo()
    {
        return realpath(__DIR__ . '/../' . $this->logoRelativo());
    }

    /** URL del logo desde una página, ej. logoUrl('../') desde /vistas o /reportes. Con cache-busting. */
    public function logoUrl($rutaBase = '../')
    {
        $rel = $this->logoRelativo();
        $fs  = __DIR__ . '/../' . $rel;
        $partes = explode('/', $rel);
        $partes[count($partes) - 1] = rawurlencode(end($partes));
        return $rutaBase . implode('/', $partes) . (is_file($fs) ? '?v=' . filemtime($fs) : '');
    }

    /**
     * Datos de la empresa para las páginas públicas (landing y tienda).
     * $rutaBase = prefijo relativo hasta la raíz del proyecto ('' desde index.php, '../' desde /tienda).
     * Devuelve el logo ya resuelto como URL (con cache-busting) y textos decodificados.
     */
    public function datosPublicos($rutaBase = '')
    {
        $decode = function ($value) {
            $txt = trim((string)$value);
            for ($i = 0; $i < 3; $i++) {
                $decoded = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($decoded === $txt) {
                    break;
                }
                $txt = $decoded;
            }
            return trim($txt);
        };

        $cfg = $this->obtener();
        if (!$cfg) {
            $cfg = array();
        }

        $logoUrl = $this->logoUrl($rutaBase);

        $nombreComercial = $decode($cfg['nombre_comercial'] ?? '');
        $razonSocial     = $decode($cfg['razon_social'] ?? '');

        return array(
            'nombre'       => $nombreComercial !== '' ? $nombreComercial : ($razonSocial !== '' ? $razonSocial : self::NOMBRE_DEFECTO),
            'razon_social' => $razonSocial,
            'ruc'          => $decode($cfg['ruc'] ?? ''),
            'direccion'    => $decode($cfg['direccion'] ?? ''),
            'telefono'     => $decode(!empty($cfg['telefono']) ? $cfg['telefono'] : ($cfg['celular'] ?? '')),
            'celular'      => $decode($cfg['celular'] ?? ''),
            'correo'       => $decode($cfg['correo'] ?? ''),
            'web'          => $decode($cfg['web'] ?? ''),
            'logo_url'     => $logoUrl,
        );
    }

    public function datosReporte()
    {
        $decode = function ($value) {
            $txt = trim((string)$value);
            for ($i = 0; $i < 3; $i++) {
                $decoded = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($decoded === $txt) {
                    break;
                }
                $txt = $decoded;
            }
            return trim($txt);
        };

        $cfg = $this->obtener();
        if (!$cfg) {
            return array(
                "nombre" => self::NOMBRE_DEFECTO,
                "nombre_comercial" => '',
                "ruc" => '',
                "direccion_linea1" => '',
                "direccion_linea2" => '',
                "telefono" => '',
                "email" => '',
                "web" => '',
                "logo" => '',
                "logo_path" => $this->logoArchivo(),
                "logo_url" => $this->logoUrl('../'),
                "moneda" => 'PEN'
            );
        }

        $direccion = $decode($cfg['direccion']);
        $direccion_linea1 = $direccion;
        $direccion_linea2 = '';
        if ($direccion !== '') {
            $lineas = preg_split('/\r\n|\r|\n/', wordwrap($direccion, 44, "\n", false));
            $direccion_linea1 = isset($lineas[0]) ? trim($lineas[0]) : '';
            $direccion_linea2 = isset($lineas[1]) ? trim($lineas[1]) : '';
        }

        return array(
            "nombre"           => $decode(!empty($cfg['razon_social']) ? $cfg['razon_social'] : $cfg['nombre_comercial']),
            "nombre_comercial" => $decode(!empty($cfg['nombre_comercial']) ? $cfg['nombre_comercial'] : ''),
            "ruc"              => $decode($cfg['ruc']),
            "direccion_linea1" => $decode($direccion_linea1),
            "direccion_linea2" => $decode($direccion_linea2),
            "telefono"         => $decode(!empty($cfg['telefono']) ? $cfg['telefono'] : $cfg['celular']),
            "email"            => $decode($cfg['correo']),
            "web"              => $decode(!empty($cfg['web']) ? $cfg['web'] : ''),
            "logo"             => !empty($cfg['logo']) ? $cfg['logo'] : '',
            // Logo listo para usar: ruta en disco (FPDF) y URL desde /reportes (ticket HTML)
            "logo_path"        => $this->logoArchivo(),
            "logo_url"         => $this->logoUrl('../'),
            "moneda"           => !empty($cfg['moneda']) ? strtoupper($cfg['moneda']) : 'PEN'
        );
    }
}
?>

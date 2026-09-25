<?php
require_once __DIR__ . "/../config/Conexion.php";

// Contenido administrable de la página pública (index.php).
// esquema() describe cada sección: sus campos, etiquetas y valores por defecto.
// El mismo esquema lo usa el editor (vistas/landing.php) para dibujar el formulario
// y este modelo para validar lo que se guarda, así nunca se desalinean.
class Landing
{
    private $tabla = "landing_config";
    const DIR_IMG = "files/landing/";

    public static function esquema()
    {
        $item = function ($campos) { return $campos; };
        return array(
            'general' => array(
                'titulo' => 'General y colores',
                'icono'  => 'bi-sliders',
                'ayuda'  => 'Nombre, logo, dirección, teléfono y correo se editan en Gestión Pro > Empresa.',
                'campos' => array(
                    'seo_titulo'      => array('tipo' => 'text', 'label' => 'Título de la pestaña del navegador', 'ayuda' => 'Usa {empresa} para insertar el nombre de la farmacia.', 'def' => '{empresa} – Al Cuidado de Tu Salud'),
                    'seo_descripcion' => array('tipo' => 'textarea', 'label' => 'Descripción para Google', 'ayuda' => 'Texto corto (máx. 160 caracteres) que aparece en los resultados de búsqueda.', 'max' => 300, 'def' => '{empresa}, tu farmacia de confianza. Medicamentos de calidad, asesoría farmacéutica y venta online. Al cuidado de tu salud.'),
                    'color_principal' => array('tipo' => 'color', 'label' => 'Color principal', 'def' => '#1D4ED8'),
                    'color_oscuro'    => array('tipo' => 'color', 'label' => 'Color principal oscuro', 'def' => '#1E3A8A'),
                    'color_acento'    => array('tipo' => 'color', 'label' => 'Color de acento (celeste)', 'def' => '#0EA5E9'),
                    'color_alerta'    => array('tipo' => 'color', 'label' => 'Color de precios y detalles (rojo)', 'def' => '#DC2626'),
                    'btn_tienda'      => array('tipo' => 'bool', 'label' => 'Mostrar botón "Tienda Online" en el menú', 'def' => true),
                    'btn_tienda_txt'  => array('tipo' => 'text', 'label' => 'Texto del botón de tienda', 'def' => 'Tienda Online'),
                    'btn_sistema'     => array('tipo' => 'bool', 'label' => 'Mostrar botón "Sistema" (acceso del personal)', 'def' => true),
                    'wa_flotante'     => array('tipo' => 'bool', 'label' => 'Mostrar botón flotante de WhatsApp', 'def' => true),
                    'wa_mensaje'      => array('tipo' => 'text', 'label' => 'Mensaje inicial de WhatsApp', 'ayuda' => 'Texto que aparece escrito al abrir el chat. Déjalo vacío si no quieres ninguno.', 'def' => ''),
                ),
            ),
            'hero' => array(
                'titulo' => 'Portada',
                'icono'  => 'bi-window',
                'campos' => array(
                    'visible'     => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'etiqueta'    => array('tipo' => 'text', 'label' => 'Etiqueta superior', 'def' => 'Tu salud, nuestra razón de ser'),
                    'mostrar_nombre' => array('tipo' => 'bool', 'label' => 'Mostrar el nombre de la farmacia en el título', 'def' => true),
                    'titulo'      => array('tipo' => 'textarea', 'label' => 'Título principal', 'ayuda' => 'Cada salto de línea se respeta.', 'max' => 200, 'def' => "Al cuidado de\ntu salud"),
                    'lema'        => array('tipo' => 'text', 'label' => 'Lema', 'def' => 'Calidad · Confianza · Compromiso'),
                    'descripcion' => array('tipo' => 'textarea', 'label' => 'Descripción', 'def' => 'Medicamentos certificados, asesoría farmacéutica personalizada y venta online desde la comodidad de tu hogar. Estamos cerca de ti para servirte.'),
                    'btn1_txt'    => array('tipo' => 'text', 'label' => 'Botón principal: texto', 'def' => 'Comprar Online'),
                    'btn1_url'    => array('tipo' => 'url', 'label' => 'Botón principal: enlace', 'def' => 'tienda/index.php'),
                    'btn2_txt'    => array('tipo' => 'text', 'label' => 'Botón secundario: texto', 'def' => 'Cómo Llegar'),
                    'btn2_url'    => array('tipo' => 'url', 'label' => 'Botón secundario: enlace', 'def' => '#contacto'),
                    'imagen'      => array('tipo' => 'image', 'label' => 'Imagen de fondo', 'def' => 'https://images.unsplash.com/photo-1576602976047-174e57a47881?w=1920&q=80'),
                    'cifras'      => array('tipo' => 'list', 'label' => 'Cifras destacadas', 'max' => 4, 'item' => $item(array(
                        'icono' => array('tipo' => 'icon', 'label' => 'Ícono'),
                        'valor' => array('tipo' => 'text', 'label' => 'Valor', 'max' => 20),
                        'texto' => array('tipo' => 'text', 'label' => 'Texto', 'max' => 40),
                    )), 'def' => array(
                        array('icono' => 'bi-capsule-pill', 'valor' => '1000+', 'texto' => 'Productos'),
                        array('icono' => 'bi-shield-fill-check', 'valor' => '100%', 'texto' => 'Garantía'),
                        array('icono' => 'bi-award-fill', 'valor' => 'Cert.', 'texto' => 'DIGEMID'),
                    )),
                    'tarjeta'       => array('tipo' => 'bool', 'label' => 'Mostrar tarjeta con logo y dirección', 'def' => true),
                    'tarjeta_texto' => array('tipo' => 'text', 'label' => 'Texto bajo el logo de la tarjeta', 'def' => 'Botica · Farmacia · Salud'),
                    'insignias'     => array('tipo' => 'list', 'label' => 'Insignias flotantes junto a la tarjeta', 'max' => 2, 'item' => $item(array(
                        'icono'  => array('tipo' => 'icon', 'label' => 'Ícono'),
                        'titulo' => array('tipo' => 'text', 'label' => 'Título', 'max' => 40),
                        'texto'  => array('tipo' => 'text', 'label' => 'Texto', 'max' => 60),
                    )), 'def' => array(
                        array('icono' => 'bi-clipboard2-pulse-fill', 'titulo' => 'Recetas Atendidas', 'texto' => 'Control especializado'),
                        array('icono' => 'bi-patch-check-fill', 'titulo' => 'Calidad Garantizada', 'texto' => 'Productos certificados'),
                    )),
                ),
            ),
            'beneficios' => array(
                'titulo' => 'Beneficios',
                'icono'  => 'bi-award',
                'ayuda'  => 'Franja de color debajo de la portada.',
                'campos' => array(
                    'visible' => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'items'   => array('tipo' => 'list', 'label' => 'Beneficios', 'max' => 4, 'item' => $item(array(
                        'icono'  => array('tipo' => 'icon', 'label' => 'Ícono'),
                        'titulo' => array('tipo' => 'text', 'label' => 'Título', 'max' => 60),
                        'texto'  => array('tipo' => 'text', 'label' => 'Texto', 'max' => 90),
                    )), 'def' => array(
                        array('icono' => 'bi-capsule-pill', 'titulo' => 'Medicamentos Garantizados', 'texto' => 'Genéricos y de marca certificados'),
                        array('icono' => 'bi-person-badge-fill', 'titulo' => 'Asesoría Farmacéutica', 'texto' => 'Orientación profesional gratuita'),
                        array('icono' => 'bi-bag-check-fill', 'titulo' => 'Compra Online Segura', 'texto' => 'Múltiples métodos de pago'),
                    )),
                ),
            ),
            'servicios' => array(
                'titulo' => 'Servicios',
                'icono'  => 'bi-grid',
                'campos' => array(
                    'visible'     => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'menu'        => array('tipo' => 'text', 'label' => 'Nombre en el menú', 'max' => 30, 'def' => 'Servicios'),
                    'etiqueta'    => array('tipo' => 'text', 'label' => 'Etiqueta', 'def' => 'Nuestros Servicios'),
                    'titulo'      => array('tipo' => 'text', 'label' => 'Título', 'ayuda' => 'Encierra entre *asteriscos* la palabra que quieres resaltar en color.', 'def' => 'Todo lo que *Necesitas* para tu Salud'),
                    'descripcion' => array('tipo' => 'textarea', 'label' => 'Descripción', 'def' => 'Atención integral con los más altos estándares farmacéuticos para cuidar a toda tu familia.'),
                    'items'       => array('tipo' => 'list', 'label' => 'Servicios', 'max' => 12, 'item' => $item(array(
                        'imagen' => array('tipo' => 'image', 'label' => 'Imagen'),
                        'icono'  => array('tipo' => 'icon', 'label' => 'Ícono'),
                        'titulo' => array('tipo' => 'text', 'label' => 'Título', 'max' => 60),
                        'texto'  => array('tipo' => 'textarea', 'label' => 'Texto', 'max' => 300),
                    )), 'def' => array(
                        array('imagen' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=600&q=80', 'icono' => 'bi-capsule-pill', 'titulo' => 'Medicamentos', 'texto' => 'Gran variedad de medicamentos genéricos y de marca. Todos con garantía de calidad y trazabilidad DIGEMID.'),
                        array('imagen' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?w=600&q=80', 'icono' => 'bi-shop-window', 'titulo' => 'Venta Online', 'texto' => 'Catálogo digital disponible 24/7. Compra desde casa con total seguridad y recibe tu comprobante electrónico.'),
                        array('imagen' => 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=600&q=80', 'icono' => 'bi-person-badge-fill', 'titulo' => 'Asesoría Farmacéutica', 'texto' => 'Farmacéuticos titulados te orientan sobre el uso correcto de medicamentos, interacciones y alternativas genéricas.'),
                        array('imagen' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&q=80', 'icono' => 'bi-heart-pulse-fill', 'titulo' => 'Cuidado Personal', 'texto' => 'Amplia gama de productos para higiene personal, dermocosméticos y bienestar familiar a los mejores precios.'),
                        array('imagen' => 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&q=80', 'icono' => 'bi-clipboard2-pulse-fill', 'titulo' => 'Recetas Médicas', 'texto' => 'Atendemos recetas con la responsabilidad que tu salud merece. Control especial de medicamentos regulados.'),
                        array('imagen' => 'https://images.unsplash.com/photo-1576602976047-174e57a47881?w=600&q=80', 'icono' => 'bi-thermometer-half', 'titulo' => 'Cadena de Frío', 'texto' => 'Control de temperatura en recepción y almacenamiento. Garantizamos la integridad de todos tus medicamentos.'),
                    )),
                ),
            ),
            'productos' => array(
                'titulo' => 'Productos',
                'icono'  => 'bi-capsule',
                'campos' => array(
                    'visible'     => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'menu'        => array('tipo' => 'text', 'label' => 'Nombre en el menú', 'max' => 30, 'def' => 'Productos'),
                    'etiqueta'    => array('tipo' => 'text', 'label' => 'Etiqueta', 'def' => 'Catálogo'),
                    'titulo'      => array('tipo' => 'text', 'label' => 'Título', 'ayuda' => 'Encierra entre *asteriscos* la palabra que quieres resaltar en color.', 'def' => 'Productos *Destacados*'),
                    'descripcion' => array('tipo' => 'textarea', 'label' => 'Descripción', 'def' => 'Selección de medicamentos y productos de salud disponibles en nuestra botica.'),
                    'modo'        => array('tipo' => 'select', 'label' => '¿Qué productos mostrar?', 'opciones' => array(
                        'recientes' => 'Los últimos registrados',
                        'vendidos'  => 'Los más vendidos (últimos 90 días)',
                        'ofertas'   => 'Solo los que están en oferta',
                        'manual'    => 'Los que yo elija',
                    ), 'def' => 'recientes'),
                    'cantidad'    => array('tipo' => 'select', 'label' => 'Cantidad a mostrar', 'opciones' => array('4' => '4', '8' => '8', '12' => '12', '16' => '16'), 'def' => '8'),
                    'elegidos'    => array('tipo' => 'products', 'label' => 'Productos elegidos', 'ayuda' => 'Solo se usa si eliges "Los que yo elija". Se muestran en este orden.', 'def' => array()),
                    'solo_stock'  => array('tipo' => 'bool', 'label' => 'Ocultar productos sin stock', 'def' => false),
                    'mostrar_precio' => array('tipo' => 'bool', 'label' => 'Mostrar precios', 'def' => true),
                    'etiqueta_producto' => array('tipo' => 'text', 'label' => 'Etiqueta sobre cada producto', 'max' => 20, 'def' => 'Disponible'),
                    'btn_txt'     => array('tipo' => 'text', 'label' => 'Texto del botón inferior', 'def' => 'Ver Catálogo Completo'),
                    'btn_url'     => array('tipo' => 'url', 'label' => 'Enlace del botón inferior', 'def' => 'tienda/index.php'),
                ),
            ),
            'banner' => array(
                'titulo' => 'Banner de llamada',
                'icono'  => 'bi-megaphone',
                'ayuda'  => 'Franja con imagen de fondo e invitación a comprar o escribir.',
                'campos' => array(
                    'visible'  => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'titulo'   => array('tipo' => 'text', 'label' => 'Título', 'def' => '¿Necesitas orientación sobre tu medicamento?'),
                    'texto'    => array('tipo' => 'textarea', 'label' => 'Texto', 'def' => 'Nuestros farmacéuticos están listos para ayudarte. Visítanos o contáctanos ahora mismo.'),
                    'btn1_txt' => array('tipo' => 'text', 'label' => 'Botón principal: texto', 'def' => 'Ir a la Tienda Online'),
                    'btn1_url' => array('tipo' => 'url', 'label' => 'Botón principal: enlace', 'def' => 'tienda/index.php'),
                    'btn_wa'   => array('tipo' => 'bool', 'label' => 'Mostrar botón de WhatsApp', 'def' => true),
                    'btn_wa_txt' => array('tipo' => 'text', 'label' => 'Texto del botón de WhatsApp', 'def' => 'Escribir por WhatsApp'),
                    'imagen'   => array('tipo' => 'image', 'label' => 'Imagen de fondo', 'def' => 'https://images.unsplash.com/photo-1631549916768-4119b2e5f926?w=1600&q=80'),
                ),
            ),
            'nosotros' => array(
                'titulo' => 'Nosotros',
                'icono'  => 'bi-people',
                'campos' => array(
                    'visible'  => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'menu'     => array('tipo' => 'text', 'label' => 'Nombre en el menú', 'max' => 30, 'def' => 'Nosotros'),
                    'etiqueta' => array('tipo' => 'text', 'label' => 'Etiqueta', 'def' => '¿Quiénes Somos?'),
                    'titulo'   => array('tipo' => 'text', 'label' => 'Título', 'ayuda' => 'Encierra entre *asteriscos* la palabra que quieres resaltar en color.', 'def' => 'Tu salud es *nuestra misión*'),
                    'texto'    => array('tipo' => 'textarea', 'label' => 'Texto', 'ayuda' => 'Usa {empresa} para insertar el nombre de la farmacia.', 'max' => 1500, 'def' => '{empresa} nació con el compromiso de brindar acceso a medicamentos de calidad a toda la comunidad. Contamos con farmacéuticos titulados, sistema digital de gestión y una plataforma de venta online para servirte mejor.'),
                    'imagen'   => array('tipo' => 'image', 'label' => 'Foto', 'def' => 'https://images.unsplash.com/photo-1576602976047-174e57a47881?w=800&q=80'),
                    'insignia_valor' => array('tipo' => 'text', 'label' => 'Insignia sobre la foto: valor', 'ayuda' => 'Déjalo vacío para ocultar la insignia.', 'max' => 10, 'def' => '+5'),
                    'insignia_texto' => array('tipo' => 'text', 'label' => 'Insignia sobre la foto: texto', 'max' => 40, 'def' => 'Años al servicio'),
                    'puntos'   => array('tipo' => 'list', 'label' => 'Puntos destacados', 'max' => 6, 'item' => $item(array(
                        'icono'  => array('tipo' => 'icon', 'label' => 'Ícono'),
                        'titulo' => array('tipo' => 'text', 'label' => 'Título', 'max' => 60),
                        'texto'  => array('tipo' => 'text', 'label' => 'Texto', 'max' => 120),
                    )), 'def' => array(
                        array('icono' => 'bi-patch-check-fill', 'titulo' => 'Calidad Certificada', 'texto' => 'Proveedores autorizados por DIGEMID. Trazabilidad completa.'),
                        array('icono' => 'bi-thermometer-half', 'titulo' => 'Cadena de Frío Controlada', 'texto' => 'Monitoreo de temperatura en recepción y almacenamiento.'),
                        array('icono' => 'bi-lock-fill', 'titulo' => 'Compra 100% Segura', 'texto' => 'Sistema de ventas con comprobantes electrónicos y seguimiento.'),
                    )),
                ),
            ),
            'mision' => array(
                'titulo' => 'Misión y Visión',
                'icono'  => 'bi-flag',
                'campos' => array(
                    'visible'     => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'etiqueta'    => array('tipo' => 'text', 'label' => 'Etiqueta', 'def' => 'Nuestro Compromiso'),
                    'titulo'      => array('tipo' => 'text', 'label' => 'Título', 'ayuda' => 'Encierra entre *asteriscos* la palabra que quieres resaltar en color.', 'def' => 'Misión y *Visión*'),
                    'descripcion' => array('tipo' => 'textarea', 'label' => 'Descripción', 'def' => 'Los principios que guían cada atención y cada medicamento que entregamos.'),
                    'mision'      => array('tipo' => 'textarea', 'label' => 'Misión', 'max' => 1500, 'def' => 'Brindar acceso a medicamentos de calidad y asesoría farmacéutica confiable a la comunidad, con atención cercana, profesionales titulados y precios justos, cuidando la salud de cada familia como si fuera la nuestra.'),
                    'vision'      => array('tipo' => 'textarea', 'label' => 'Visión', 'max' => 1500, 'def' => 'Ser la botica de referencia de la región por su calidad de servicio, innovación digital y compromiso con la salud pública, expandiendo nuestra plataforma online para acercar medicamentos certificados a más comunidades cada año.'),
                ),
            ),
            'contacto' => array(
                'titulo' => 'Contacto y horario',
                'icono'  => 'bi-geo-alt',
                'ayuda'  => 'Dirección, teléfono y correo se toman de Gestión Pro > Empresa.',
                'campos' => array(
                    'visible'     => array('tipo' => 'bool', 'label' => 'Mostrar esta sección', 'def' => true),
                    'menu'        => array('tipo' => 'text', 'label' => 'Nombre en el menú', 'max' => 30, 'def' => 'Contacto'),
                    'etiqueta'    => array('tipo' => 'text', 'label' => 'Etiqueta', 'def' => 'Contáctanos'),
                    'titulo'      => array('tipo' => 'text', 'label' => 'Título', 'ayuda' => 'Encierra entre *asteriscos* la palabra que quieres resaltar en color.', 'def' => 'Estamos Aquí para *Ayudarte*'),
                    'descripcion' => array('tipo' => 'textarea', 'label' => 'Descripción', 'def' => 'Visítanos, llámanos o escríbenos. Siempre hay un farmacéutico listo para atenderte.'),
                    'horario'     => array('tipo' => 'textarea', 'label' => 'Horario de atención', 'ayuda' => 'Una línea por cada horario.', 'max' => 400, 'def' => "Lunes – Sábado: 8:00 am – 10:00 pm\nDomingo: 9:00 am – 8:00 pm"),
                    'mapa_embed'  => array('tipo' => 'url', 'label' => 'Mapa de Google (enlace para insertar)', 'ayuda' => 'En Google Maps: Compartir > Insertar un mapa > copia solo el enlace que está dentro de src="...". Si lo dejas vacío se muestra un botón "Abrir en Google Maps".', 'def' => ''),
                    'btn_wa_txt'  => array('tipo' => 'text', 'label' => 'Texto del botón de WhatsApp', 'def' => 'Escribir por WhatsApp'),
                ),
            ),
            'pie' => array(
                'titulo' => 'Redes y pie de página',
                'icono'  => 'bi-share',
                'campos' => array(
                    'descripcion' => array('tipo' => 'textarea', 'label' => 'Descripción de la farmacia en el pie', 'def' => 'Tu botica de confianza. Al cuidado de tu salud y de toda tu familia con calidad, responsabilidad y el más alto estándar farmacéutico.'),
                    'facebook'    => array('tipo' => 'url', 'label' => 'Facebook (enlace a tu página)', 'ayuda' => 'Déjalo vacío para ocultar el ícono.', 'def' => ''),
                    'instagram'   => array('tipo' => 'url', 'label' => 'Instagram', 'def' => ''),
                    'tiktok'      => array('tipo' => 'url', 'label' => 'TikTok', 'def' => ''),
                    'youtube'     => array('tipo' => 'url', 'label' => 'YouTube', 'def' => ''),
                    'whatsapp'    => array('tipo' => 'bool', 'label' => 'Mostrar ícono de WhatsApp', 'def' => true),
                    'copyright'   => array('tipo' => 'text', 'label' => 'Texto de derechos', 'ayuda' => 'Se antepone "© año nombre de la farmacia".', 'def' => '– Al cuidado de tu salud. Todos los derechos reservados.'),
                    'firma'       => array('tipo' => 'text', 'label' => 'Texto final (derecha)', 'ayuda' => 'Déjalo vacío para ocultarlo.', 'def' => 'Desarrollado con ♥ para la salud de tu familia'),
                ),
            ),
        );
    }

    // Valores por defecto de una sección (o de todas)
    public static function defaults($seccion = null)
    {
        $out = array();
        foreach (self::esquema() as $clave => $sec) {
            $out[$clave] = array();
            foreach ($sec['campos'] as $campo => $def) {
                $out[$clave][$campo] = $def['def'];
            }
        }
        return $seccion === null ? $out : (isset($out[$seccion]) ? $out[$seccion] : array());
    }

    // Configuración efectiva: lo guardado encima de los valores por defecto.
    // Si la tabla no existe (migración pendiente) devuelve solo los defaults.
    public function obtener()
    {
        $cfg = self::defaults();
        $rs = ejecutarConsulta("SELECT clave, valor FROM {$this->tabla}");
        if ($rs) {
            while ($row = $rs->fetch_assoc()) {
                if (!isset($cfg[$row['clave']])) continue;
                $guardado = json_decode($row['valor'], true);
                if (!is_array($guardado)) continue;
                foreach ($cfg[$row['clave']] as $campo => $v) {
                    if (array_key_exists($campo, $guardado)) $cfg[$row['clave']][$campo] = $guardado[$campo];
                }
            }
        }
        return $cfg;
    }

    public function tablaDisponible()
    {
        $rs = ejecutarConsulta("SHOW TABLES LIKE '{$this->tabla}'");
        return $rs && $rs->num_rows > 0;
    }

    public function guardar($seccion, $datos)
    {
        global $conexion;
        $esq = self::esquema();
        if (!isset($esq[$seccion])) {
            return array('ok' => false, 'message' => 'Sección desconocida');
        }
        if (!$this->tablaDisponible()) {
            return array('ok' => false, 'message' => 'Falta aplicar la migración 20260925_landing_config.sql en la base de datos.');
        }
        $limpio = array();
        foreach ($esq[$seccion]['campos'] as $campo => $def) {
            $limpio[$campo] = self::limpiarValor($def, isset($datos[$campo]) ? $datos[$campo] : null);
        }
        $json = $conexion->real_escape_string(json_encode($limpio, JSON_UNESCAPED_UNICODE));
        $ok = ejecutarConsulta("INSERT INTO {$this->tabla} (clave, valor) VALUES ('$seccion', '$json')
                                ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
        return $ok
            ? array('ok' => true, 'message' => 'Cambios publicados en la página web', 'data' => $limpio)
            : array('ok' => false, 'message' => 'No se pudo guardar. Inténtalo nuevamente.');
    }

    public function restaurar($seccion)
    {
        if (!isset(self::esquema()[$seccion])) {
            return array('ok' => false, 'message' => 'Sección desconocida');
        }
        if ($this->tablaDisponible()) {
            ejecutarConsulta("DELETE FROM {$this->tabla} WHERE clave='$seccion'");
        }
        return array('ok' => true, 'message' => 'Se restauraron los textos originales de la sección', 'data' => self::defaults($seccion));
    }

    // Los textos se guardan planos (sin HTML) y se escapan al mostrarlos en index.php.
    // No se usa limpiarCadena() aquí: su htmlspecialchars dejaría entidades (&amp;quot;)
    // dentro del JSON y se verían al editar. SQL se protege con real_escape_string en guardar().
    private static function limpiarValor($def, $v)
    {
        switch ($def['tipo']) {
            case 'bool':
                return ($v === true || $v === 1 || $v === '1' || $v === 'true');
            case 'color':
                return (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? strtoupper($v) : $def['def'];
            case 'icon':
                return (is_string($v) && preg_match('/^bi-[a-z0-9-]{1,40}$/', $v)) ? $v : 'bi-circle-fill';
            case 'select':
                $v = (string)$v;
                return isset($def['opciones'][$v]) ? $v : $def['def'];
            case 'url':
                return self::limpiarUrl($v);
            case 'image':
                return self::limpiarImagen($v);
            case 'products':
                $ids = array();
                foreach ((array)$v as $id) {
                    $id = (int)$id;
                    if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
                }
                return array_slice($ids, 0, 16);
            case 'list':
                $items = array();
                foreach (array_slice((array)$v, 0, $def['max']) as $fila) {
                    if (!is_array($fila)) continue;
                    $item = array();
                    $vacio = true;
                    foreach ($def['item'] as $campo => $defItem) {
                        $item[$campo] = self::limpiarValor($defItem, isset($fila[$campo]) ? $fila[$campo] : '');
                        if ($defItem['tipo'] !== 'icon' && $item[$campo] !== '') $vacio = false;
                    }
                    if (!$vacio) $items[] = $item;
                }
                return $items;
            case 'textarea':
            case 'text':
            default:
                $max = isset($def['max']) ? $def['max'] : ($def['tipo'] === 'textarea' ? 600 : 150);
                $txt = trim(strip_tags(str_replace("\r\n", "\n", (string)$v)));
                return mb_substr($txt, 0, $max);
        }
    }

    // Enlaces permitidos: http(s), anclas (#seccion) y rutas relativas del sitio. Nunca javascript:
    private static function limpiarUrl($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (preg_match('#^https?://[^\s"<>]+$#i', $v)) return mb_substr($v, 0, 600);
        if (preg_match('#^\#[A-Za-z0-9_-]*$#', $v)) return $v;
        if (preg_match('#^(mailto:|tel:)[^\s"<>]+$#i', $v)) return $v;
        if (preg_match('#^[A-Za-z0-9_./?=&%-]+$#', $v) && strpos($v, '..') === false && stripos($v, 'javascript') === false) return $v;
        return '';
    }

    // Imágenes: subidas al servidor (files/landing/...) o enlaces https externos
    private static function limpiarImagen($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (preg_match('#^https://[^\s"<>\']+$#i', $v)) return mb_substr($v, 0, 600);
        if (preg_match('#^' . preg_quote(self::DIR_IMG, '#') . '[A-Za-z0-9_-]+\.(jpg|jpeg|png|gif)$#i', $v)) return $v;
        return '';
    }

    public function subirImagen($archivo)
    {
        if (!$archivo || !isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
            return array('ok' => false, 'message' => 'Selecciona una imagen para subir');
        }
        if ($archivo['size'] > 3 * 1024 * 1024) {
            return array('ok' => false, 'message' => 'La imagen pesa más de 3 MB. Redúcela e inténtalo de nuevo.');
        }
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $info = @getimagesize($archivo['tmp_name']);
        $mimes = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif');
        if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif'), true) || !$info || !isset($mimes[$info['mime']])) {
            return array('ok' => false, 'message' => 'Solo se aceptan imágenes JPG, PNG o GIF');
        }
        $dir = __DIR__ . '/../' . self::DIR_IMG;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return array('ok' => false, 'message' => 'No se pudo crear la carpeta de imágenes');
        }
        $nombre = 'landing_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $mimes[$info['mime']];
        if (!move_uploaded_file($archivo['tmp_name'], $dir . $nombre)) {
            return array('ok' => false, 'message' => 'No se pudo guardar la imagen');
        }
        return array('ok' => true, 'message' => 'Imagen subida', 'data' => array('ruta' => self::DIR_IMG . $nombre));
    }

    // Productos para la sección "Productos destacados" según el modo elegido
    public function productosDestacados($cfgProd)
    {
        $limite = max(1, min(16, (int)$cfgProd['cantidad']));
        $precio = sqlPrecioFinalExpr('a');
        $base   = sqlPrecioBaseExpr('a');
        $oferta = sqlOfertaVigenteExpr('a');
        $where  = "a.condicion=1";
        if (!empty($cfgProd['solo_stock'])) $where .= " AND a.stock>0";
        $join   = "";
        $orden  = "a.idarticulo DESC";

        switch ($cfgProd['modo']) {
            case 'ofertas':
                $where .= " AND $oferta";
                break;
            case 'vendidos':
                $join  = "INNER JOIN (SELECT dv.idarticulo, SUM(dv.cantidad) AS vendidos
                                      FROM detalle_venta dv INNER JOIN venta v ON v.idventa=dv.idventa
                                      WHERE v.estado='Aceptado' AND v.fecha_hora >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                                      GROUP BY dv.idarticulo) top ON top.idarticulo=a.idarticulo";
                $orden = "top.vendidos DESC";
                break;
            case 'manual':
                $ids = array_map('intval', (array)$cfgProd['elegidos']);
                if (!$ids) return array();
                $lista = implode(',', $ids);
                $where .= " AND a.idarticulo IN ($lista)";
                $orden = "FIELD(a.idarticulo, $lista)";
                break;
        }

        $sql = "SELECT a.idarticulo AS id, a.nombre, a.imagen, c.nombre AS categoria,
                       $precio AS precio_venta, $base AS precio_original, IF($oferta,1,0) AS en_oferta, a.descuento_porcentaje
                FROM articulo a
                LEFT JOIN categoria c ON c.idcategoria=a.idcategoria
                $join
                WHERE $where
                ORDER BY $orden
                LIMIT $limite";
        $out = array();
        $rs = ejecutarConsulta($sql);
        if ($rs) while ($row = $rs->fetch_assoc()) $out[] = $row;
        return $out;
    }

    // Buscador del editor para elegir productos a mano
    public function buscarArticulos($texto, $ids = array())
    {
        global $conexion;
        if ($ids) {
            $lista = implode(',', array_map('intval', $ids));
            $sql = "SELECT idarticulo AS id, nombre, imagen FROM articulo WHERE idarticulo IN ($lista) AND condicion=1 ORDER BY FIELD(idarticulo, $lista)";
        } else {
            $t = $conexion->real_escape_string(trim((string)$texto));
            $sql = "SELECT idarticulo AS id, nombre, imagen FROM articulo
                    WHERE condicion=1 AND (nombre LIKE '%$t%' OR codigo LIKE '%$t%' OR principio_activo LIKE '%$t%')
                    ORDER BY nombre ASC LIMIT 15";
        }
        $out = array();
        $rs = ejecutarConsulta($sql);
        if ($rs) while ($row = $rs->fetch_assoc()) $out[] = array('id' => (int)$row['id'], 'nombre' => $row['nombre'], 'imagen' => $row['imagen']);
        return $out;
    }
}

<?php
require_once "config/Conexion.php";
require_once "modelos/Empresa.php";

// Datos de la empresa: todo sale de configuracion_empresa (Acceso > Empresa)
$empresa   = (new Empresa())->datosPublicos('');
$nombreEmp = $empresa['nombre'];
$telefono  = $empresa['telefono'] !== '' ? $empresa['telefono'] : '+51 999 999 999';
$correo    = $empresa['correo']   !== '' ? $empresa['correo']   : 'contacto@farmasuyana.com';
$ruc       = $empresa['ruc'];
$waNro     = preg_replace('/\D/', '', $telefono);
$direccion = $empresa['direccion'] !== '' ? $empresa['direccion'] : 'Urb. Patibamba Baja, Av. Sinchi Roca Lote 1 – al Costado de la Iglesia Cristiana';
$logoUrl   = $empresa['logo_url'] !== '' ? $empresa['logo_url'] : 'files/famacia.png';

// Todo el contenido restante se administra en Gestión Pro > Página web (modelos/Landing.php)
require_once "modelos/Landing.php";
$landingMdl = new Landing();
$L = $landingMdl->obtener();
$G = $L['general'];
$productos = $L['productos']['visible'] ? $landingMdl->productosDestacados($L['productos']) : [];
$simbolo = obtenerSimboloMoneda();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
// Datos de artículos: se guardan con entidades HTML (limpiarCadena), se decodifican antes de escapar
function eDb($s) { return e(html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
// {empresa} → nombre comercial
function emp($s) { global $nombreEmp; return str_replace('{empresa}', $nombreEmp, (string)$s); }
// Título con *palabra resaltada*
function tit($s) { return preg_replace('/\*([^*]+)\*/', '<em>$1</em>', e(emp($s))); }
// Texto multilínea
function nl($s) { return nl2br(e(emp($s)), false); }
// Imagen: enlace externo o archivo subido (files/landing/...), ya validado al guardar
function img($s) { return e($s); }
function cssUrl($s) { return $s === '' ? 'none' : 'url("' . str_replace(array('"', '\\', '<', '>'), '', $s) . '")'; }

$waLink = 'https://wa.me/' . $waNro . ($G['wa_mensaje'] !== '' ? '?text=' . rawurlencode(emp($G['wa_mensaje'])) : '');

// Menú: solo secciones visibles
$menu = [['#inicio', 'Inicio', 'bi-house-heart-fill']];
foreach ([['servicios', 'bi-capsule-pill'], ['productos', 'bi-grid-fill'], ['nosotros', 'bi-heart-pulse-fill'], ['contacto', 'bi-geo-alt-fill']] as [$sec, $ico]) {
  if ($L[$sec]['visible']) $menu[] = ['#' . $sec, $L[$sec]['menu'], $ico];
}
$redes = [];
foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'] as $red => $nomRed) {
  if ($L['pie'][$red] !== '') $redes[] = [$L['pie'][$red], $nomRed, 'bi-' . $red];
}
if ($L['pie']['whatsapp']) $redes[] = [$waLink, 'WhatsApp', 'bi-whatsapp'];
$mapaEmbed = preg_match('#^https://(www\.)?google\.[a-z.]+/maps/embed#i', $L['contacto']['mapa_embed']) ? $L['contacto']['mapa_embed'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(emp($G['seo_titulo'])) ?></title>
<meta name="description" content="<?= e(emp($G['seo_descripcion'])) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
<!-- Bootstrap Icons (moderno 2025) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<!-- Font Awesome 4 para compatibilidad sistema -->
<link rel="stylesheet" href="public/css/font-awesome.min.css">
<style>
/* ═══ TOKENS ═══════════════════════════════════════════════ */
:root{
  --blue:       #1D4ED8;  /* azul royal – identidad */
  --blue-dk:    #1E3A8A;  /* azul oscuro – hover */
  --blue-md:    #2563EB;  /* azul medio */
  --cyan:       #0EA5E9;  /* celeste – acento logo */
  --cyan-lt:    #BAE6FD;  /* celeste claro */
  --red:        #DC2626;  /* rojo cruz – acento */
  --red-dk:     #B91C1C;
  --white:      #ffffff;
  --gray-50:    #F8FAFC;
  --gray-100:   #F1F5F9;
  --gray-200:   #E2E8F0;
  --gray-500:   #64748B;
  --gray-700:   #334155;
  --gray-900:   #0F172A;
  --grad-blue:  linear-gradient(135deg,#1E3A8A 0%,#1D4ED8 55%,#0EA5E9 100%);
  --grad-red:   linear-gradient(135deg,#DC2626,#B91C1C);
  --grad-hero:  linear-gradient(135deg,rgba(30,58,138,.93) 0%,rgba(29,78,216,.88) 55%,rgba(185,28,28,.82) 100%);
  --shadow:     0 4px 20px rgba(15,23,42,.10);
  --shadow-lg:  0 12px 40px rgba(15,23,42,.16);
  --shadow-blue:0 8px 28px rgba(29,78,216,.35);
  --r:14px; --r-lg:20px; --r-xl:28px;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Inter',system-ui,sans-serif;color:var(--gray-900);background:#fff;overflow-x:hidden}
a{text-decoration:none;color:inherit}
img{max-width:100%;display:block}

/* ═══ NAVBAR ══════════════════════════════════════════════ */
.nav{
  position:fixed;top:0;left:0;right:0;z-index:1000;
  background:rgba(255,255,255,.97);backdrop-filter:blur(16px);
  border-bottom:1px solid rgba(29,78,216,.10);
  box-shadow:0 1px 20px rgba(15,23,42,.07);
}
.nav-inner{
  max-width:1280px;margin:0 auto;padding:0 5%;
  height:96px;display:flex;align-items:center;justify-content:space-between;gap:24px;
}
.nav-logo img{height:80px;object-fit:contain}
.nav-links{display:flex;align-items:center;gap:28px;list-style:none}
.nav-links a{
  font-size:.82rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
  color:var(--gray-700);transition:color .2s;
}
.nav-links a:hover{color:var(--blue)}
.nav-actions{display:flex;gap:10px;align-items:center}

/* Botones */
.btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:10px 22px;border-radius:50px;font-family:inherit;
  font-size:.82rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
  cursor:pointer;transition:all .25s;border:none;
}
.btn-blue{background:var(--grad-blue);color:#fff;box-shadow:var(--shadow-blue)}
.btn-blue:hover{transform:translateY(-2px);box-shadow:0 12px 32px rgba(29,78,216,.50);color:#fff}
.btn-red{background:var(--grad-red);color:#fff;box-shadow:0 6px 20px rgba(220,38,38,.35)}
.btn-red:hover{transform:translateY(-2px);color:#fff}
.btn-outline-blue{border:2px solid var(--blue);color:var(--blue)}
.btn-outline-blue:hover{background:var(--blue);color:#fff}
.btn-ghost{border:1.5px solid var(--gray-200);color:var(--gray-700)}
.btn-ghost:hover{border-color:var(--blue);color:var(--blue)}

.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer}
.hamburger span{display:block;width:26px;height:2.5px;background:var(--gray-700);border-radius:2px;transition:.3s}
.mob-menu{
  display:none;flex-direction:column;
  position:fixed;top:72px;left:0;right:0;z-index:999;
  background:#fff;box-shadow:var(--shadow-lg);
  border-top:3px solid var(--blue);
}
.mob-menu.open{display:flex}
.mob-menu a{
  padding:14px 5%;font-size:.9rem;font-weight:600;letter-spacing:.03em;text-transform:uppercase;
  color:var(--gray-700);border-bottom:1px solid var(--gray-100);transition:color .2s;
  display:flex;align-items:center;gap:10px;
}
.mob-menu a i{color:var(--blue);font-size:16px;width:20px}
.mob-menu a:hover{color:var(--blue);background:var(--gray-50)}
.mob-actions{display:flex;gap:10px;padding:14px 5%}

/* ═══ HERO ════════════════════════════════════════════════ */
.hero{
  min-height:100vh;position:relative;overflow:hidden;
  display:flex;align-items:center;padding:88px 5% 60px;
}
.hero-bg{
  position:absolute;inset:0;z-index:0;
  background:
    var(--grad-hero),
    url('https://images.unsplash.com/photo-1576602976047-174e57a47881?w=1920&q=80') center/cover no-repeat;
}
.hero-bg::after{
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='80' height='80' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='40' cy='40' r='1.5' fill='%23ffffff' fill-opacity='.06'/%3E%3C/svg%3E");
}
/* Decoración ECG */
.hero-ecg{
  position:absolute;bottom:0;left:0;right:0;z-index:1;
  height:60px;opacity:.15;
  background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 60'%3E%3Cpath d='M0 30h280l20-20 15 40 20-50 15 50 20-40 20 20H1440' stroke='%23fff' stroke-width='2.5' fill='none'/%3E%3C/svg%3E") center/contain repeat-x;
}
.hero-inner{
  position:relative;z-index:2;
  max-width:1280px;margin:0 auto;width:100%;
  display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;
}
.hero-eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.30);
  color:#fff;padding:6px 16px;border-radius:50px;
  font-size:.72rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;
  margin-bottom:20px;backdrop-filter:blur(4px);
}
.hero-eyebrow i{color:var(--cyan-lt);font-size:14px}
.hero h1{
  font-family:'Sora',sans-serif;
  font-size:clamp(2.2rem,4.5vw,3.8rem);font-weight:800;color:#fff;
  line-height:1.10;margin-bottom:8px;letter-spacing:-.03em;
}
.hero h1 .brand-name{
  display:block;
  background:linear-gradient(90deg,#fff 0%,var(--cyan-lt) 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.hero-tagline{
  font-size:1.1rem;font-weight:600;color:rgba(255,255,255,.85);
  margin-bottom:28px;letter-spacing:.01em;
}
.hero-tagline span{color:var(--cyan-lt)}
.hero-sub{
  font-size:.97rem;color:rgba(255,255,255,.75);
  line-height:1.75;margin-bottom:36px;max-width:500px;
}
.hero-btns{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:40px}
.btn-hero-w{
  background:#fff;color:var(--blue);
  padding:13px 28px;border-radius:50px;
  font-weight:800;font-size:.88rem;letter-spacing:.04em;text-transform:uppercase;
  box-shadow:0 6px 24px rgba(0,0,0,.18);transition:all .25s;
  display:inline-flex;align-items:center;gap:8px;
}
.btn-hero-w:hover{transform:translateY(-3px);box-shadow:0 10px 32px rgba(0,0,0,.28);color:var(--blue)}
.btn-hero-brd{
  border:2px solid rgba(255,255,255,.65);color:#fff;
  padding:13px 28px;border-radius:50px;
  font-weight:700;font-size:.88rem;letter-spacing:.04em;text-transform:uppercase;
  transition:background .2s;display:inline-flex;align-items:center;gap:8px;
}
.btn-hero-brd:hover{background:rgba(255,255,255,.12);color:#fff}
.hero-stats{display:flex;gap:20px;flex-wrap:wrap}
.hs{
  display:flex;align-items:center;gap:10px;
  background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.18);
  padding:12px 18px;border-radius:14px;backdrop-filter:blur(6px);
}
.hs-icon{
  width:40px;height:40px;border-radius:10px;
  background:rgba(255,255,255,.20);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;color:#fff;flex-shrink:0;
}
.hs-text strong{display:block;font-size:1.15rem;font-weight:800;color:#fff;line-height:1}
.hs-text span{font-size:.7rem;color:rgba(255,255,255,.72);text-transform:uppercase;font-weight:600;letter-spacing:.05em}

/* Hero Card (right) */
.hero-card{
  background:rgba(255,255,255,.97);border-radius:var(--r-xl);
  padding:36px 32px 32px;box-shadow:0 24px 72px rgba(0,0,0,.30);
  text-align:center;max-width:360px;width:100%;margin:0 auto;
}
.hero-card img{width:100%;max-width:340px;margin:0 auto 16px;display:block}
.hero-card-tag{
  font-size:.72rem;font-weight:700;letter-spacing:.10em;text-transform:uppercase;
  background:var(--grad-blue);-webkit-background-clip:text;-webkit-text-fill-color:transparent;
  margin-bottom:16px;
}
.hero-card-addr{
  font-size:.78rem;color:var(--gray-500);line-height:1.6;
  border-top:1px solid var(--gray-100);padding-top:14px;
  display:flex;align-items:flex-start;gap:8px;
}
.hero-card-addr i{color:var(--red);font-size:16px;flex-shrink:0;margin-top:1px}
/* Floating badges */
.hero-float{position:relative}
.fb{
  position:absolute;background:#fff;border-radius:14px;
  padding:10px 14px;box-shadow:var(--shadow-lg);
  display:flex;align-items:center;gap:10px;
}
.fb-ic{
  width:36px;height:36px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;
}
.fb-ic-blue{background:var(--grad-blue)}
.fb-ic-red{background:var(--grad-red)}
.fb-txt strong{display:block;font-size:.8rem;font-weight:700;color:var(--gray-900)}
.fb-txt span{font-size:.68rem;color:var(--gray-500)}
.fb-tr{top:-16px;right:-16px}
.fb-bl{bottom:-16px;left:-16px}

/* ═══ STRIP ══════════════════════════════════════════════ */
.strip{background:var(--grad-blue);padding:0}
.strip-in{
  max-width:1280px;margin:0 auto;
  display:grid;grid-template-columns:repeat(3,1fr);
}
.strip-item{
  display:flex;align-items:center;gap:14px;
  padding:22px 28px;border-right:1px solid rgba(255,255,255,.18);
  transition:background .2s;
}
.strip-item:last-child{border-right:none}
.strip-item:hover{background:rgba(255,255,255,.08)}
.si-icon{
  width:46px;height:46px;border-radius:12px;flex-shrink:0;
  background:rgba(255,255,255,.20);
  display:flex;align-items:center;justify-content:center;
  font-size:22px;color:#fff;
}
.si-txt strong{display:block;color:#fff;font-weight:700;font-size:.95rem}
.si-txt span{color:rgba(255,255,255,.78);font-size:.8rem}

/* ═══ SECCIÓN GENÉRICA ══════════════════════════════════ */
section{padding:88px 5%}
.sec-in{max-width:1280px;margin:0 auto}
.sec-hd{text-align:center;margin-bottom:60px}
.sec-tag{
  display:inline-flex;align-items:center;gap:6px;
  padding:5px 16px;border-radius:50px;
  background:rgba(29,78,216,.08);border:1px solid rgba(29,78,216,.18);
  font-size:.72rem;font-weight:800;color:var(--blue);
  letter-spacing:.10em;text-transform:uppercase;margin-bottom:14px;
}
.sec-tag i{font-size:12px}
.sec-hd h2{
  font-family:'Sora',sans-serif;
  font-size:clamp(1.8rem,3vw,2.6rem);font-weight:800;line-height:1.18;margin-bottom:12px;
}
.sec-hd h2 em{
  font-style:normal;
  background:var(--grad-blue);-webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.sec-hd p{font-size:1rem;color:var(--gray-500);max-width:560px;margin:0 auto;line-height:1.75}

/* ═══ SERVICIOS ══════════════════════════════════════════ */
.servicios{background:var(--gray-50)}
.srv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.srv-card{
  background:#fff;border-radius:var(--r-xl);overflow:hidden;
  box-shadow:var(--shadow);border:1px solid var(--gray-100);
  transition:transform .3s,box-shadow .3s;
}
.srv-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-blue)}
.srv-img{height:190px;position:relative;overflow:hidden}
.srv-img img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.srv-card:hover .srv-img img{transform:scale(1.08)}
.srv-img-overlay{
  position:absolute;inset:0;
  background:linear-gradient(180deg,transparent 35%,rgba(15,23,42,.65));
}
.srv-icon-badge{
  position:absolute;top:14px;left:14px;
  width:46px;height:46px;border-radius:14px;
  background:var(--grad-blue);
  display:flex;align-items:center;justify-content:center;
  font-size:22px;color:#fff;box-shadow:var(--shadow-blue);
}
.srv-body{padding:20px 22px 24px}
.srv-body h3{font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;margin-bottom:8px}
.srv-body p{font-size:.875rem;color:var(--gray-500);line-height:1.65}

/* ═══ PRODUCTOS ══════════════════════════════════════════ */
.prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.prod-card{
  background:#fff;border-radius:var(--r-lg);overflow:hidden;
  border:1px solid var(--gray-100);
  box-shadow:var(--shadow);transition:all .3s;
}
.prod-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-blue);border-color:transparent}
.prod-img-w{
  height:164px;background:var(--gray-50);
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;position:relative;
}
.prod-img-w img{max-height:148px;max-width:100%;object-fit:contain;padding:10px;transition:transform .3s}
.prod-card:hover .prod-img-w img{transform:scale(1.08)}
.prod-img-w .no-img{font-size:52px;opacity:.22}
.prod-avail{
  position:absolute;top:10px;right:10px;
  background:var(--grad-blue);color:#fff;
  font-size:.6rem;font-weight:800;padding:3px 10px;border-radius:20px;
  text-transform:uppercase;letter-spacing:.06em;
}
.prod-body{padding:14px 16px 18px}
.prod-cat{font-size:.67rem;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
.prod-name{font-family:'Sora',sans-serif;font-size:.88rem;font-weight:700;margin-bottom:10px;line-height:1.3}
.prod-footer{display:flex;align-items:center;justify-content:space-between}
.prod-price{font-size:1.12rem;font-weight:800;color:var(--red)}
.prod-price sub{font-size:.68rem;color:var(--gray-500);font-weight:400}
.prod-add{
  width:36px;height:36px;border-radius:10px;
  background:var(--grad-blue);display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:16px;transition:transform .2s,box-shadow .2s;flex-shrink:0;
}
.prod-add:hover{transform:scale(1.12);box-shadow:var(--shadow-blue)}
.empty-p{
  text-align:center;padding:60px 20px;color:var(--gray-500);grid-column:1/-1;
}
.empty-p i{font-size:56px;opacity:.3;color:var(--blue);display:block;margin-bottom:14px}
.ver-mas{text-align:center;margin-top:44px}

/* ═══ CTA BANNER ═════════════════════════════════════════ */
.cta-sec{position:relative;overflow:hidden;padding:100px 5%;text-align:center;color:#fff}
.cta-bg{
  position:absolute;inset:0;z-index:0;
  background:
    linear-gradient(135deg,rgba(30,58,138,.94) 0%,rgba(29,78,216,.90) 60%,rgba(185,28,28,.88) 100%),
    url('https://images.unsplash.com/photo-1631549916768-4119b2e5f926?w=1600&q=80') center/cover no-repeat;
}
.cta-bg::before{
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='40' height='40' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='20' cy='20' r='1' fill='%23fff' fill-opacity='.05'/%3E%3C/svg%3E");
}
.cta-sec .sec-in{position:relative;z-index:1}
.cta-sec h2{font-family:'Sora',sans-serif;font-size:clamp(2rem,4vw,3rem);font-weight:800;margin-bottom:14px}
.cta-sec p{font-size:1.05rem;opacity:.88;max-width:540px;margin:0 auto 36px;line-height:1.75}
.cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.btn-cta-w{
  background:#fff;color:var(--blue-dk);padding:14px 32px;border-radius:50px;
  font-weight:800;font-size:.9rem;letter-spacing:.04em;text-transform:uppercase;
  box-shadow:0 6px 20px rgba(0,0,0,.18);transition:all .25s;
  display:inline-flex;align-items:center;gap:8px;
}
.btn-cta-w:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.28);color:var(--blue-dk)}
.btn-cta-brd{
  border:2px solid rgba(255,255,255,.65);color:#fff;padding:14px 32px;
  border-radius:50px;font-weight:700;font-size:.9rem;
  letter-spacing:.04em;text-transform:uppercase;transition:background .2s;
  display:inline-flex;align-items:center;gap:8px;
}
.btn-cta-brd:hover{background:rgba(255,255,255,.12);color:#fff}

/* ═══ NOSOTROS ═══════════════════════════════════════════ */
.nosotros{background:#fff}
.nos-grid{display:grid;grid-template-columns:1fr 1fr;gap:72px;align-items:center}
.nos-img-wrap{position:relative}
.nos-main-img{
  width:100%;height:460px;object-fit:cover;
  border-radius:var(--r-xl);box-shadow:var(--shadow-lg);
}
.nos-badge{
  position:absolute;bottom:-18px;right:-18px;
  background:#fff;border-radius:var(--r-lg);padding:18px 22px;
  box-shadow:var(--shadow-lg);text-align:center;min-width:130px;
}
.nos-badge strong{
  display:block;font-family:'Sora',sans-serif;
  font-size:2.4rem;font-weight:900;line-height:1;
  background:var(--grad-blue);-webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.nos-badge span{font-size:.75rem;color:var(--gray-500);font-weight:600}
.nos-content .sec-tag{display:inline-flex;margin-bottom:14px}
.nos-content h2{
  font-family:'Sora',sans-serif;
  font-size:clamp(1.8rem,3vw,2.4rem);font-weight:800;line-height:1.2;margin-bottom:18px;
}
.nos-content h2 em{
  font-style:normal;
  background:var(--grad-blue);-webkit-background-clip:text;-webkit-text-fill-color:transparent;
}
.nos-content p{font-size:.975rem;color:var(--gray-500);line-height:1.8;margin-bottom:28px}
.nos-feats{display:flex;flex-direction:column;gap:14px}
.nos-feat{
  display:flex;align-items:flex-start;gap:14px;
  padding:16px 18px;background:var(--gray-50);
  border-radius:var(--r);border-left:3px solid var(--blue);
}
.nf-ico{
  width:42px;height:42px;border-radius:10px;flex-shrink:0;
  background:var(--grad-blue);
  display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;
}
.nf-tx strong{display:block;font-weight:700;font-size:.9rem;margin-bottom:2px}
.nf-tx span{font-size:.82rem;color:var(--gray-500)}

/* ═══ MISIÓN Y VISIÓN ═══════════════════════════════════════ */
.mision-vision{background:var(--gray-50)}
.mv-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px}
.mv-card{
  background:#fff;border-radius:var(--r-xl);padding:40px 36px;
  box-shadow:var(--shadow);border-top:4px solid var(--blue);
  transition:transform .3s ease,box-shadow .3s ease;
}
.mv-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg)}
.mv-card.red{border-top-color:var(--red)}
.mv-ico{
  width:58px;height:58px;border-radius:16px;
  background:var(--grad-blue);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:26px;margin-bottom:22px;box-shadow:var(--shadow-blue);
}
.mv-card.red .mv-ico{background:var(--grad-red);box-shadow:0 8px 28px rgba(220,38,38,.35)}
.mv-card h3{font-family:'Sora',sans-serif;font-weight:800;font-size:1.35rem;margin-bottom:14px}
.mv-card p{font-size:.95rem;color:var(--gray-500);line-height:1.85}

/* ═══ CONTACTO ════════════════════════════════════════════ */
.contacto{background:var(--gray-50)}
.cnt-grid{display:grid;grid-template-columns:1fr 1fr;gap:48px}
.cnt-info h3{font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:800;margin-bottom:26px}
.cnt-item{display:flex;align-items:flex-start;gap:14px;margin-bottom:22px}
.ci-ico{
  width:48px;height:48px;border-radius:14px;flex-shrink:0;
  background:var(--grad-blue);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:20px;box-shadow:var(--shadow-blue);
}
.ci-tx strong{display:block;font-weight:700;font-size:.92rem;margin-bottom:2px}
.ci-tx span{font-size:.85rem;color:var(--gray-500);line-height:1.5}
.wa-btn{
  display:inline-flex;align-items:center;gap:10px;margin-top:8px;
  background:#25D366;color:#fff;padding:13px 26px;border-radius:50px;
  font-weight:700;font-size:.88rem;letter-spacing:.04em;text-transform:uppercase;
  box-shadow:0 4px 16px rgba(37,211,102,.4);transition:all .25s;
}
.wa-btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(37,211,102,.55);color:#fff}
.cnt-map{
  border-radius:var(--r-xl);height:400px;background:#fff;
  box-shadow:var(--shadow);border:1px solid var(--gray-100);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;
}
.cnt-map i{font-size:64px;color:var(--blue);opacity:.4}
.cnt-map h4{font-family:'Sora',sans-serif;font-weight:700;font-size:1.1rem}
.cnt-map p{font-size:.875rem;color:var(--gray-500);text-align:center;max-width:280px;line-height:1.6}

/* ═══ FOOTER ══════════════════════════════════════════════ */
footer{background:#060E24;color:#94A3B8;padding:64px 5% 32px}
.foot-in{max-width:1280px;margin:0 auto}
.foot-top{display:grid;grid-template-columns:2.2fr 1fr 1fr 1.6fr;gap:48px;margin-bottom:48px}
.foot-brand img{height:82px;margin-bottom:18px}
.foot-brand p{font-size:.875rem;line-height:1.75;max-width:290px;color:#475569}
.foot-social{display:flex;gap:10px;margin-top:20px}
.foot-social a{
  width:38px;height:38px;border-radius:10px;
  background:rgba(255,255,255,.06);display:flex;align-items:center;
  justify-content:center;color:#64748B;font-size:18px;transition:all .2s;
}
.foot-social a:hover{background:var(--blue);color:#fff;transform:translateY(-2px)}
.foot-col h5{
  font-family:'Sora',sans-serif;color:#F1F5F9;
  font-weight:700;font-size:.85rem;letter-spacing:.07em;
  text-transform:uppercase;margin-bottom:20px;
}
.foot-col ul{list-style:none;display:flex;flex-direction:column;gap:10px}
.foot-col ul li a{
  color:#475569;font-size:.875rem;transition:color .2s;
  display:flex;align-items:center;gap:8px;
}
.foot-col ul li a i{font-size:12px;color:var(--cyan);width:14px}
.foot-col ul li a:hover{color:var(--cyan)}
.foot-hr{border:none;border-top:1px solid rgba(255,255,255,.07);margin-bottom:28px}
.foot-bot{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.foot-bot p{font-size:.8rem;color:#334155}
.foot-bot a{color:var(--cyan)}

/* ═══ WHATSAPP FAB ═══════════════════════════════════════ */
.wa-fab{
  position:fixed;bottom:28px;right:28px;z-index:1000;
  width:60px;height:60px;border-radius:50%;
  background:#25D366;display:flex;align-items:center;justify-content:center;
  font-size:28px;color:#fff;box-shadow:0 4px 20px rgba(37,211,102,.50);
  animation:wa-ring 2.5s infinite;
}
@keyframes wa-ring{
  0%,100%{box-shadow:0 4px 20px rgba(37,211,102,.5),0 0 0 0 rgba(37,211,102,.35)}
  50%{box-shadow:0 8px 30px rgba(37,211,102,.65),0 0 0 16px rgba(37,211,102,0)}
}

/* ═══ REVEAL ══════════════════════════════════════════════ */
.rev{opacity:0;transform:translateY(28px);transition:opacity .6s ease,transform .6s ease}
.rev.vis{opacity:1;transform:none}
.rev-l{opacity:0;transform:translateX(-28px);transition:opacity .6s ease,transform .6s ease}
.rev-l.vis{opacity:1;transform:none}
.rev-r{opacity:0;transform:translateX(28px);transition:opacity .6s ease,transform .6s ease}
.rev-r.vis{opacity:1;transform:none}
.d1{transition-delay:.10s}.d2{transition-delay:.20s}
.d3{transition-delay:.30s}.d4{transition-delay:.40s}

/* ═══ RESPONSIVE ══════════════════════════════════════════ */
@media(max-width:1100px){.prod-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:980px){
  .nav-links,.nav-actions{display:none}
  .hamburger{display:flex}
  .hero-inner{grid-template-columns:1fr}
  .hero-card{display:none}
  .srv-grid{grid-template-columns:repeat(2,1fr)}
  .prod-grid{grid-template-columns:repeat(2,1fr)}
  .nos-grid{grid-template-columns:1fr}
  .nos-img-wrap{max-width:500px;margin:0 auto}
  .mv-grid{grid-template-columns:1fr}
  .cnt-grid{grid-template-columns:1fr}
  .strip-in{grid-template-columns:1fr}
  .strip-item{border-right:none;border-bottom:1px solid rgba(255,255,255,.15)}
  .strip-item:last-child{border-bottom:none}
  .foot-top{grid-template-columns:1fr 1fr}
}
@media(max-width:600px){
  section{padding:64px 4%}
  .srv-grid,.prod-grid{grid-template-columns:1fr}
  .hero-stats{flex-direction:column;gap:10px}
  .foot-top{grid-template-columns:1fr}
  .foot-bot{flex-direction:column;text-align:center}
  .cta-btns{flex-direction:column;align-items:center}
}
/* ═══ CONTENIDO ADMINISTRABLE (Gestión Pro > Página web) ═══ */
.prod-price del{display:block;font-size:.72rem;color:var(--gray-500);font-weight:600}
.prod-avail.oferta{background:var(--grad-red)}
.cnt-map iframe{width:100%;height:100%;border:0;border-radius:var(--r-xl)}
.cnt-map.con-mapa{padding:0;overflow:hidden}
.nav-spacer{height:96px}
</style>
<style>
:root{
  --blue:<?= e($G['color_principal']) ?>;
  --blue-dk:<?= e($G['color_oscuro']) ?>;
  --blue-md:<?= e($G['color_principal']) ?>;
  --cyan:<?= e($G['color_acento']) ?>;
  --red:<?= e($G['color_alerta']) ?>;
  --red-dk:color-mix(in srgb,var(--red) 80%,#000);
  --grad-blue:linear-gradient(135deg,var(--blue-dk) 0%,var(--blue) 55%,var(--cyan) 100%);
  --grad-red:linear-gradient(135deg,var(--red),var(--red-dk));
  --grad-hero:linear-gradient(135deg,color-mix(in srgb,var(--blue-dk) 93%,transparent) 0%,color-mix(in srgb,var(--blue) 88%,transparent) 55%,color-mix(in srgb,var(--red-dk) 82%,transparent) 100%);
  --shadow-blue:0 8px 28px color-mix(in srgb,var(--blue) 35%,transparent);
}
.hero-bg{background:var(--grad-hero),<?= cssUrl($L['hero']['imagen']) ?> center/cover no-repeat}
.cta-bg{background:linear-gradient(135deg,color-mix(in srgb,var(--blue-dk) 94%,transparent) 0%,color-mix(in srgb,var(--blue) 90%,transparent) 60%,color-mix(in srgb,var(--red-dk) 88%,transparent) 100%),<?= cssUrl($L['banner']['imagen']) ?> center/cover no-repeat}
</style>
</head>
<body>

<!-- ── NAVBAR ─────────────────────────────────────────────── -->
<nav class="nav" id="mainNav">
  <div class="nav-inner">
    <a href="#inicio" class="nav-logo">
      <img src="<?= e($logoUrl) ?>" alt="<?= e($nombreEmp) ?>">
    </a>
    <ul class="nav-links">
      <?php foreach ($menu as [$href, $txt]): ?>
      <li><a href="<?= $href ?>"><?= e($txt) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="nav-actions">
      <?php if ($G['btn_tienda']): ?>
      <a href="tienda/index.php" class="btn btn-blue">
        <i class="bi bi-bag-heart-fill"></i> <?= e($G['btn_tienda_txt']) ?>
      </a>
      <?php endif; ?>
      <?php if ($G['btn_sistema']): ?>
      <a href="vistas/login.html" class="btn btn-ghost">
        <i class="bi bi-person-lock"></i> Sistema
      </a>
      <?php endif; ?>
    </div>
    <div class="hamburger" id="hambBtn" onclick="toggleMenu()">
      <span></span><span></span><span></span>
    </div>
  </div>
</nav>

<!-- Mobile Menu -->
<div class="mob-menu" id="mobMenu">
  <?php foreach ($menu as [$href, $txt, $ico]): ?>
  <a href="<?= $href ?>" onclick="closeMenu()"><i class="bi <?= $ico ?>"></i> <?= e($txt) ?></a>
  <?php endforeach; ?>
  <div class="mob-actions">
    <?php if ($G['btn_tienda']): ?>
    <a href="tienda/index.php" class="btn btn-blue" style="flex:1;justify-content:center">
      <i class="bi bi-bag-heart-fill"></i> <?= e($G['btn_tienda_txt']) ?>
    </a>
    <?php endif; ?>
    <?php if ($G['btn_sistema']): ?>
    <a href="vistas/login.html" class="btn btn-ghost" style="flex:1;justify-content:center">
      <i class="bi bi-person-lock"></i> Sistema
    </a>
    <?php endif; ?>
  </div>
</div>

<?php $H = $L['hero']; if ($H['visible']): ?>
<!-- ── HERO ───────────────────────────────────────────────── -->
<section id="inicio" class="hero">
  <div class="hero-bg"></div>
  <div class="hero-ecg"></div>
  <div class="hero-inner">

    <div class="hero-left">
      <?php if ($H['etiqueta'] !== ''): ?>
      <div class="hero-eyebrow rev">
        <i class="bi bi-heart-pulse-fill"></i>
        <?= e(emp($H['etiqueta'])) ?>
      </div>
      <?php endif; ?>
      <h1 class="rev d1">
        <?php if ($H['mostrar_nombre']): ?><span class="brand-name"><?= e($nombreEmp) ?></span><?php endif; ?>
        <?= nl($H['titulo']) ?>
      </h1>
      <?php if ($H['lema'] !== ''): ?>
      <p class="hero-tagline rev d2"><span><?= e(emp($H['lema'])) ?></span></p>
      <?php endif; ?>
      <?php if ($H['descripcion'] !== ''): ?>
      <p class="hero-sub rev d2"><?= nl($H['descripcion']) ?></p>
      <?php endif; ?>
      <div class="hero-btns rev d3">
        <?php if ($H['btn1_txt'] !== ''): ?>
        <a href="<?= e($H['btn1_url']) ?>" class="btn-hero-w">
          <i class="bi bi-bag-heart-fill"></i> <?= e($H['btn1_txt']) ?>
        </a>
        <?php endif; ?>
        <?php if ($H['btn2_txt'] !== ''): ?>
        <a href="<?= e($H['btn2_url']) ?>" class="btn-hero-brd">
          <i class="bi bi-geo-alt-fill"></i> <?= e($H['btn2_txt']) ?>
        </a>
        <?php endif; ?>
      </div>
      <?php if ($H['cifras']): ?>
      <div class="hero-stats rev d4">
        <?php foreach ($H['cifras'] as $c): ?>
        <div class="hs">
          <div class="hs-icon"><i class="bi <?= e($c['icono']) ?>"></i></div>
          <div class="hs-text"><strong><?= e($c['valor']) ?></strong><span><?= e($c['texto']) ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($H['tarjeta']): ?>
    <div class="hero-right rev-r d2">
      <div class="hero-float">
        <div class="hero-card">
          <img src="<?= e($logoUrl) ?>" alt="<?= e($nombreEmp) ?>">
          <?php if ($H['tarjeta_texto'] !== ''): ?><div class="hero-card-tag"><?= e($H['tarjeta_texto']) ?></div><?php endif; ?>
          <div class="hero-card-addr">
            <i class="bi bi-geo-alt-fill"></i>
            <span><?= e($direccion) ?></span>
          </div>
        </div>
        <?php foreach ($H['insignias'] as $i => $b): ?>
        <div class="fb <?= $i === 0 ? 'fb-tr' : 'fb-bl' ?>">
          <div class="fb-ic <?= $i === 0 ? 'fb-ic-blue' : 'fb-ic-red' ?>"><i class="bi <?= e($b['icono']) ?>"></i></div>
          <div class="fb-txt">
            <strong><?= e($b['titulo']) ?></strong>
            <span><?= e($b['texto']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>
<?php else: ?>
<div id="inicio" class="nav-spacer"></div>
<?php endif; ?>

<?php if ($L['beneficios']['visible'] && $L['beneficios']['items']): ?>
<!-- ── STRIP ──────────────────────────────────────────────── -->
<div class="strip">
  <div class="strip-in" style="grid-template-columns:repeat(<?= count($L['beneficios']['items']) ?>,1fr)">
    <?php foreach ($L['beneficios']['items'] as $b): ?>
    <div class="strip-item">
      <div class="si-icon"><i class="bi <?= e($b['icono']) ?>"></i></div>
      <div class="si-txt">
        <strong><?= e($b['titulo']) ?></strong>
        <span><?= e($b['texto']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php $S = $L['servicios']; if ($S['visible']): ?>
<!-- ── SERVICIOS ──────────────────────────────────────────── -->
<section id="servicios" class="servicios">
  <div class="sec-in">
    <div class="sec-hd rev">
      <div class="sec-tag"><i class="bi bi-grid-fill"></i> <?= e($S['etiqueta']) ?></div>
      <h2><?= tit($S['titulo']) ?></h2>
      <?php if ($S['descripcion'] !== ''): ?><p><?= nl($S['descripcion']) ?></p><?php endif; ?>
    </div>
    <div class="srv-grid">
      <?php foreach ($S['items'] as $i => $s): ?>
      <div class="srv-card rev d<?= ($i % 3) + 1 ?>">
        <div class="srv-img">
          <?php if ($s['imagen'] !== ''): ?><img src="<?= img($s['imagen']) ?>" alt="<?= e($s['titulo']) ?>" loading="lazy"><?php endif; ?>
          <div class="srv-img-overlay"></div>
          <div class="srv-icon-badge"><i class="bi <?= e($s['icono']) ?>"></i></div>
        </div>
        <div class="srv-body">
          <h3><?= e($s['titulo']) ?></h3>
          <p><?= nl($s['texto']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $P = $L['productos']; if ($P['visible']): ?>
<!-- ── PRODUCTOS DESTACADOS ───────────────────────────────── -->
<section id="productos">
  <div class="sec-in">
    <div class="sec-hd rev">
      <div class="sec-tag"><i class="bi bi-stars"></i> <?= e($P['etiqueta']) ?></div>
      <h2><?= tit($P['titulo']) ?></h2>
      <?php if ($P['descripcion'] !== ''): ?><p><?= nl($P['descripcion']) ?></p><?php endif; ?>
    </div>

    <?php if (!empty($productos)): ?>
    <div class="prod-grid">
      <?php foreach ($productos as $i => $p): $d = ($i%4)+1; ?>
      <div class="prod-card rev d<?= $d ?>">
        <div class="prod-img-w">
          <?php if (!empty($p['imagen']) && file_exists("files/articulos/".$p['imagen'])): ?>
            <img src="files/articulos/<?= e(rawurlencode($p['imagen'])) ?>" alt="<?= eDb($p['nombre']) ?>" loading="lazy">
          <?php else: ?>
            <div class="no-img"><i class="bi bi-capsule-pill" style="font-size:52px;opacity:.22;color:var(--blue)"></i></div>
          <?php endif; ?>
          <?php if ($p['en_oferta']): ?>
            <div class="prod-avail oferta">-<?= e(rtrim(rtrim(number_format((float)$p['descuento_porcentaje'], 2), '0'), '.')) ?>%</div>
          <?php elseif ($P['etiqueta_producto'] !== ''): ?>
            <div class="prod-avail"><?= e($P['etiqueta_producto']) ?></div>
          <?php endif; ?>
        </div>
        <div class="prod-body">
          <div class="prod-cat"><?= eDb($p['categoria'] ?? 'General') ?></div>
          <div class="prod-name"><?= eDb($p['nombre']) ?></div>
          <div class="prod-footer">
            <?php if ($P['mostrar_precio'] && (float)$p['precio_venta'] > 0): ?>
            <div class="prod-price">
              <?php if ($p['en_oferta']): ?><del><?= e($simbolo) ?> <?= number_format((float)$p['precio_original'], 2) ?></del><?php endif; ?>
              <?= e($simbolo) ?> <?= number_format((float)$p['precio_venta'], 2) ?>
              <sub>c/u</sub>
            </div>
            <?php else: ?><span></span><?php endif; ?>
            <a href="tienda/index.php" class="prod-add" title="Ver en tienda">
              <i class="bi bi-bag-plus-fill"></i>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="prod-grid">
      <div class="empty-p">
        <i class="bi bi-capsule-pill"></i>
        <h3>Catálogo en preparación</h3>
        <p>Visita nuestra tienda online para ver todos los productos disponibles.</p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($P['btn_txt'] !== ''): ?>
    <div class="ver-mas rev">
      <a href="<?= e($P['btn_url']) ?>" class="btn btn-blue" style="font-size:.95rem;padding:14px 36px">
        <i class="bi bi-grid-fill"></i> <?= e($P['btn_txt']) ?>
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php $B = $L['banner']; if ($B['visible']): ?>
<!-- ── CTA BANNER ─────────────────────────────────────────── -->
<div class="cta-sec">
  <div class="cta-bg"></div>
  <div class="sec-in rev">
    <h2><?= e(emp($B['titulo'])) ?></h2>
    <?php if ($B['texto'] !== ''): ?><p><?= nl($B['texto']) ?></p><?php endif; ?>
    <div class="cta-btns">
      <?php if ($B['btn1_txt'] !== ''): ?>
      <a href="<?= e($B['btn1_url']) ?>" class="btn-cta-w">
        <i class="bi bi-bag-heart-fill"></i> <?= e($B['btn1_txt']) ?>
      </a>
      <?php endif; ?>
      <?php if ($B['btn_wa']): ?>
      <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="btn-cta-brd">
        <i class="bi bi-whatsapp"></i> <?= e($B['btn_wa_txt']) ?>
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php $N = $L['nosotros']; if ($N['visible']): ?>
<!-- ── NOSOTROS ───────────────────────────────────────────── -->
<section id="nosotros" class="nosotros">
  <div class="sec-in">
    <div class="nos-grid">
      <div class="nos-img-wrap rev-l">
        <?php if ($N['imagen'] !== ''): ?>
        <img src="<?= img($N['imagen']) ?>" alt="<?= e($nombreEmp) ?>" class="nos-main-img" loading="lazy">
        <?php endif; ?>
        <?php if ($N['insignia_valor'] !== ''): ?>
        <div class="nos-badge">
          <strong><?= e($N['insignia_valor']) ?></strong>
          <span><?= e($N['insignia_texto']) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <div class="nos-content rev-r">
        <div class="sec-tag"><i class="bi bi-info-circle-fill"></i> <?= e($N['etiqueta']) ?></div>
        <h2><?= tit($N['titulo']) ?></h2>
        <p><?= nl($N['texto']) ?></p>
        <div class="nos-feats">
          <?php foreach ($N['puntos'] as $f): ?>
          <div class="nos-feat">
            <div class="nf-ico"><i class="bi <?= e($f['icono']) ?>"></i></div>
            <div class="nf-tx">
              <strong><?= e($f['titulo']) ?></strong>
              <span><?= e($f['texto']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $M = $L['mision']; if ($M['visible']): ?>
<!-- ── MISIÓN Y VISIÓN ────────────────────────────────────── -->
<section id="mision-vision" class="mision-vision">
  <div class="sec-in">
    <div class="sec-hd rev">
      <div class="sec-tag"><i class="bi bi-flag-fill"></i> <?= e($M['etiqueta']) ?></div>
      <h2><?= tit($M['titulo']) ?></h2>
      <?php if ($M['descripcion'] !== ''): ?><p><?= nl($M['descripcion']) ?></p><?php endif; ?>
    </div>
    <div class="mv-grid">
      <div class="mv-card rev-l">
        <div class="mv-ico"><i class="bi bi-bullseye"></i></div>
        <h3>Misión</h3>
        <p><?= nl($M['mision']) ?></p>
      </div>
      <div class="mv-card red rev-r">
        <div class="mv-ico"><i class="bi bi-binoculars-fill"></i></div>
        <h3>Visión</h3>
        <p><?= nl($M['vision']) ?></p>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php $C = $L['contacto']; if ($C['visible']): ?>
<!-- ── CONTACTO ───────────────────────────────────────────── -->
<section id="contacto" class="contacto">
  <div class="sec-in">
    <div class="sec-hd rev">
      <div class="sec-tag"><i class="bi bi-geo-alt-fill"></i> <?= e($C['etiqueta']) ?></div>
      <h2><?= tit($C['titulo']) ?></h2>
      <?php if ($C['descripcion'] !== ''): ?><p><?= nl($C['descripcion']) ?></p><?php endif; ?>
    </div>
    <div class="cnt-grid">
      <div class="rev-l">
        <h3>Información de Contacto</h3>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-geo-alt-fill"></i></div>
          <div class="ci-tx">
            <strong>Dirección</strong>
            <span><?= e($direccion) ?></span>
          </div>
        </div>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-telephone-fill"></i></div>
          <div class="ci-tx">
            <strong>Teléfono / WhatsApp</strong>
            <span><?= e($telefono) ?></span>
          </div>
        </div>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-envelope-fill"></i></div>
          <div class="ci-tx">
            <strong>Correo Electrónico</strong>
            <span><?= e($correo) ?></span>
          </div>
        </div>
        <?php if ($C['horario'] !== ''): ?>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-clock-fill"></i></div>
          <div class="ci-tx">
            <strong>Horario de Atención</strong>
            <span><?= nl($C['horario']) ?></span>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($C['btn_wa_txt'] !== ''): ?>
        <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="wa-btn">
          <i class="bi bi-whatsapp" style="font-size:20px"></i>
          <?= e($C['btn_wa_txt']) ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="rev-r">
        <?php if ($mapaEmbed !== ''): ?>
        <div class="cnt-map con-mapa">
          <iframe src="<?= e($mapaEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Ubicación de <?= e($nombreEmp) ?>"></iframe>
        </div>
        <?php else: ?>
        <div class="cnt-map">
          <i class="bi bi-geo-alt-fill"></i>
          <h4><?= e($nombreEmp) ?></h4>
          <p><?= e($direccion) ?></p>
          <a href="https://maps.google.com/?q=<?= urlencode($direccion) ?>"
             target="_blank" rel="noopener" class="btn btn-blue" style="font-size:.8rem;padding:10px 22px;margin-top:6px">
            <i class="bi bi-map-fill"></i> Abrir en Google Maps
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── FOOTER ─────────────────────────────────────────────── -->
<footer>
  <div class="foot-in">
    <div class="foot-top">
      <div class="foot-brand">
        <img src="<?= e($logoUrl) ?>" alt="<?= e($nombreEmp) ?>">
        <p><?= nl($L['pie']['descripcion']) ?></p>
        <?php if ($redes): ?>
        <div class="foot-social">
          <?php foreach ($redes as [$url, $nomRed, $ico]): ?>
          <a href="<?= e($url) ?>" title="<?= $nomRed ?>" target="_blank" rel="noopener"><i class="bi <?= $ico ?>"></i></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="foot-col">
        <h5>Navegación</h5>
        <ul>
          <?php foreach ($menu as [$href, $txt]): ?>
          <li><a href="<?= $href ?>"><i class="bi bi-chevron-right"></i> <?= e($txt) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="foot-col">
        <h5>Accesos</h5>
        <ul>
          <li><a href="tienda/index.php"><i class="bi bi-bag-heart-fill"></i> Tienda Online</a></li>
          <li><a href="tienda/login.php"><i class="bi bi-person-circle"></i> Mi Cuenta</a></li>
          <?php if ($G['btn_sistema']): ?>
          <li><a href="vistas/login.html"><i class="bi bi-gear-fill"></i> Sistema Admin</a></li>
          <?php endif; ?>
          <?php if ($C['visible']): ?>
          <li><a href="#contacto"><i class="bi bi-headset"></i> Soporte</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="foot-col">
        <h5>Contacto</h5>
        <ul>
          <li>
            <a href="https://maps.google.com/?q=<?= urlencode($direccion) ?>" target="_blank" rel="noopener">
              <i class="bi bi-geo-alt-fill"></i> <?= e($direccion) ?>
            </a>
          </li>
          <li>
            <a href="tel:<?= $waNro ?>">
              <i class="bi bi-telephone-fill"></i> <?= e($telefono) ?>
            </a>
          </li>
          <li>
            <a href="mailto:<?= e($correo) ?>">
              <i class="bi bi-envelope-fill"></i> <?= e($correo) ?>
            </a>
          </li>
          <?php if ($ruc): ?>
          <li><a href="#"><i class="bi bi-card-text"></i> RUC: <?= e($ruc) ?></a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <hr class="foot-hr">
    <div class="foot-bot">
      <p>&copy; <?= date('Y') ?> <a href="#inicio"><?= e($nombreEmp) ?></a> <?= e(emp($L['pie']['copyright'])) ?></p>
      <?php if ($L['pie']['firma'] !== ''): ?>
      <p style="color:#334155;font-size:.78rem"><?= e(emp($L['pie']['firma'])) ?></p>
      <?php endif; ?>
    </div>
  </div>
</footer>

<?php if ($G['wa_flotante']): ?>
<!-- WhatsApp flotante -->
<a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="wa-fab" title="WhatsApp">
  <i class="bi bi-whatsapp"></i>
</a>
<?php endif; ?>

<script>
window.addEventListener('scroll',()=>{
  const n=document.getElementById('mainNav');
  n.style.boxShadow=window.scrollY>40?'0 4px 28px rgba(15,23,42,.14)':'0 1px 20px rgba(15,23,42,.07)';
});
function toggleMenu(){document.getElementById('mobMenu').classList.toggle('open')}
function closeMenu(){document.getElementById('mobMenu').classList.remove('open')}
document.addEventListener('click',e=>{
  const m=document.getElementById('mobMenu'),b=document.getElementById('hambBtn');
  if(!m.contains(e.target)&&!b.contains(e.target)) m.classList.remove('open');
});
const io=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting) e.target.classList.add('vis')});
},{threshold:0.1});
document.querySelectorAll('.rev,.rev-l,.rev-r').forEach(el=>io.observe(el));
document.querySelectorAll('a[href^="#"]').forEach(a=>{
  a.addEventListener('click',e=>{
    const t=document.querySelector(a.getAttribute('href'));
    if(t){e.preventDefault();t.scrollIntoView({behavior:'smooth',block:'start'})}
  });
});
</script>
</body>
</html>

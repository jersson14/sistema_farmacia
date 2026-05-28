<?php
require_once "config/Conexion.php";

$empresa = [];
$stmt = $conexion->query("SELECT * FROM configuracion_empresa LIMIT 1");
if ($stmt && $stmt->num_rows > 0) $empresa = $stmt->fetch_assoc();

$telefono  = $empresa['telefono'] ?? '+51 999 999 999';
$correo    = $empresa['correo']   ?? 'contacto@farmasuyana.com';
$ruc       = $empresa['ruc']      ?? '';
$waNro     = preg_replace('/\D/', '', $telefono);
$direccion = 'Urb. Patibamba Baja, Av. Sinchi Roca Lote 1 – al Costado de la Iglesia Cristiana';

$productos = [];
$sqlProd = "SELECT a.idarticulo AS id, a.nombre, a.imagen,
                   IFNULL((SELECT di.precio_venta FROM detalle_ingreso di
                            WHERE di.idarticulo=a.idarticulo
                            ORDER BY di.iddetalle_ingreso DESC LIMIT 1),0) AS precio_venta,
                   c.nombre AS categoria
            FROM articulo a
            LEFT JOIN categoria c ON a.idcategoria=c.idcategoria
            WHERE a.condicion='1'
            ORDER BY a.idarticulo DESC LIMIT 8";
$rProd = $conexion->query($sqlProd);
if ($rProd) while ($row = $rProd->fetch_assoc()) $productos[] = $row;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Botica FarmaSuyana – Al Cuidado de Tu Salud</title>
<meta name="description" content="Botica FarmaSuyana, tu farmacia de confianza. Medicamentos de calidad, asesoría farmacéutica y venta online. Al cuidado de tu salud en Patibamba.">
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
</style>
</head>
<body>

<!-- ── NAVBAR ─────────────────────────────────────────────── -->
<nav class="nav" id="mainNav">
  <div class="nav-inner">
    <a href="#inicio" class="nav-logo">
      <img src="files/famacia.png" alt="Botica FarmaSuyana">
    </a>
    <ul class="nav-links">
      <li><a href="#inicio">Inicio</a></li>
      <li><a href="#servicios">Servicios</a></li>
      <li><a href="#productos">Productos</a></li>
      <li><a href="#nosotros">Nosotros</a></li>
      <li><a href="#contacto">Contacto</a></li>
    </ul>
    <div class="nav-actions">
      <a href="tienda/index.php" class="btn btn-blue">
        <i class="bi bi-bag-heart-fill"></i> Tienda Online
      </a>
      <a href="vistas/login.html" class="btn btn-ghost">
        <i class="bi bi-person-lock"></i> Sistema
      </a>
    </div>
    <div class="hamburger" id="hambBtn" onclick="toggleMenu()">
      <span></span><span></span><span></span>
    </div>
  </div>
</nav>

<!-- Mobile Menu -->
<div class="mob-menu" id="mobMenu">
  <a href="#inicio"    onclick="closeMenu()"><i class="bi bi-house-heart-fill"></i> Inicio</a>
  <a href="#servicios" onclick="closeMenu()"><i class="bi bi-capsule-pill"></i> Servicios</a>
  <a href="#productos" onclick="closeMenu()"><i class="bi bi-grid-fill"></i> Productos</a>
  <a href="#nosotros"  onclick="closeMenu()"><i class="bi bi-heart-pulse-fill"></i> Nosotros</a>
  <a href="#contacto"  onclick="closeMenu()"><i class="bi bi-geo-alt-fill"></i> Contacto</a>
  <div class="mob-actions">
    <a href="tienda/index.php" class="btn btn-blue" style="flex:1;justify-content:center">
      <i class="bi bi-bag-heart-fill"></i> Tienda
    </a>
    <a href="vistas/login.html" class="btn btn-ghost" style="flex:1;justify-content:center">
      <i class="bi bi-person-lock"></i> Sistema
    </a>
  </div>
</div>

<!-- ── HERO ───────────────────────────────────────────────── -->
<section id="inicio" class="hero">
  <div class="hero-bg"></div>
  <div class="hero-ecg"></div>
  <div class="hero-inner">

    <div class="hero-left">
      <div class="hero-eyebrow rev">
        <i class="bi bi-heart-pulse-fill"></i>
        Tu salud, nuestra razón de ser
      </div>
      <h1 class="rev d1">
        <span class="brand-name">Botica FarmaSuyana</span>
        Al cuidado de<br>tu salud
      </h1>
      <p class="hero-tagline rev d2">
        <span>Calidad · Confianza · Compromiso</span>
      </p>
      <p class="hero-sub rev d2">
        Medicamentos certificados, asesoría farmacéutica personalizada
        y venta online desde la comodidad de tu hogar.
        Estamos en el corazón de Patibamba para servirte.
      </p>
      <div class="hero-btns rev d3">
        <a href="tienda/index.php" class="btn-hero-w">
          <i class="bi bi-bag-heart-fill"></i> Comprar Online
        </a>
        <a href="#contacto" class="btn-hero-brd">
          <i class="bi bi-geo-alt-fill"></i> Cómo Llegar
        </a>
      </div>
      <div class="hero-stats rev d4">
        <div class="hs">
          <div class="hs-icon"><i class="bi bi-capsule-pill"></i></div>
          <div class="hs-text"><strong>1000+</strong><span>Productos</span></div>
        </div>
        <div class="hs">
          <div class="hs-icon"><i class="bi bi-shield-check-fill"></i></div>
          <div class="hs-text"><strong>100%</strong><span>Garantía</span></div>
        </div>
        <div class="hs">
          <div class="hs-icon"><i class="bi bi-award-fill"></i></div>
          <div class="hs-text"><strong>Cert.</strong><span>DIGEMID</span></div>
        </div>
      </div>
    </div>

    <div class="hero-right rev-r d2">
      <div class="hero-float">
        <div class="hero-card">
          <img src="files/famacia.png" alt="Botica FarmaSuyana">
          <div class="hero-card-tag">Botica · Farmacia · Salud</div>
          <div class="hero-card-addr">
            <i class="bi bi-geo-alt-fill"></i>
            <span><?= htmlspecialchars($direccion) ?></span>
          </div>
        </div>
        <div class="fb fb-tr">
          <div class="fb-ic fb-ic-blue"><i class="bi bi-clipboard2-pulse-fill"></i></div>
          <div class="fb-txt">
            <strong>Recetas Atendidas</strong>
            <span>Control especializado</span>
          </div>
        </div>
        <div class="fb fb-bl">
          <div class="fb-ic fb-ic-red"><i class="bi bi-patch-check-fill"></i></div>
          <div class="fb-txt">
            <strong>Calidad Garantizada</strong>
            <span>Productos certificados</span>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ── STRIP ──────────────────────────────────────────────── -->
<div class="strip">
  <div class="strip-in">
    <div class="strip-item">
      <div class="si-icon"><i class="bi bi-capsule-pill"></i></div>
      <div class="si-txt">
        <strong>Medicamentos Garantizados</strong>
        <span>Genéricos y de marca certificados</span>
      </div>
    </div>
    <div class="strip-item">
      <div class="si-icon"><i class="bi bi-person-badge-fill"></i></div>
      <div class="si-txt">
        <strong>Asesoría Farmacéutica</strong>
        <span>Orientación profesional gratuita</span>
      </div>
    </div>
    <div class="strip-item">
      <div class="si-icon"><i class="bi bi-bag-check-fill"></i></div>
      <div class="si-txt">
        <strong>Compra Online Segura</strong>
        <span>Múltiples métodos de pago</span>
      </div>
    </div>
  </div>
</div>

<!-- ── SERVICIOS ──────────────────────────────────────────── -->
<section id="servicios" class="servicios">
  <div class="sec-in">
    <div class="sec-hd rev">
      <div class="sec-tag"><i class="bi bi-grid-fill"></i> Nuestros Servicios</div>
      <h2>Todo lo que <em>Necesitas</em> para tu Salud</h2>
      <p>Atención integral con los más altos estándares farmacéuticos para cuidar a toda tu familia.</p>
    </div>
    <div class="srv-grid">

      <div class="srv-card rev d1">
        <div class="srv-img">
          <img src="https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=600&q=80" alt="Medicamentos">
          <div class="srv-img-overlay"></div>
          <div class="srv-icon-badge"><i class="bi bi-capsule-pill"></i></div>
        </div>
        <div class="srv-body">
          <h3>Medicamentos</h3>
          <p>Gran variedad de medicamentos genéricos y de marca. Todos con garantía de calidad y trazabilidad DIGEMID.</p>
        </div>
      </div>

      <div class="srv-card rev d2">
        <div class="srv-img">
          <img src="https://images.unsplash.com/photo-1587854692152-cbe660dbde88?w=600&q=80" alt="Venta Online">
          <div class="srv-img-overlay"></div>
          <div class="srv-icon-badge"><i class="bi bi-shop-window"></i></div>
        </div>
        <div class="srv-body">
          <h3>Venta Online</h3>
          <p>Catálogo digital disponible 24/7. Compra desde casa con total seguridad y recibe tu comprobante electrónico.</p>
        </div>
      </div>

      <div class="srv-card rev d3">
        <div class="srv-img">
          <img src="https://images.unsplash.com/photo-1559757148-5c350d0d3c56?w=600&q=80" alt="Asesoría Farmacéutica">
          <div class="srv-img-overlay"></div>
          <div class="srv-icon-badge"><i class="bi bi-person-badge-fill"></i></div>
        </div>
        <div class="srv-body">
          <h3>Asesoría Farmacéutica</h3>
          <p>Farmacéuticos titulados te orientan sobre el uso correcto de medicamentos, interacciones y alternativas genéricas.</p>
        </div>
      </div>

      <div class="srv-card rev d1">
        <div class="srv-img">
          <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&q=80" alt="Cuidado Personal">
          <div class="srv-img-overlay"></div>
          <div class="srv-icon-badge"><i class="bi bi-heart-pulse-fill"></i></div>
        </div>
        <div class="srv-body">
          <h3>Cuidado Personal</h3>
          <p>Amplia gama de productos para higiene personal, dermocosméticos y bienestar familiar a los mejores precios.</p>
        </div>
      </div>

      <div class="srv-card rev d2">
        <div class="srv-img">
          <img src="https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&q=80" alt="Recetas Médicas">
          <div class="srv-img-overlay"></div>
          <div class="srv-icon-badge"><i class="bi bi-clipboard2-pulse-fill"></i></div>
        </div>
        <div class="srv-body">
          <h3>Recetas Médicas</h3>
          <p>Atendemos recetas con la responsabilidad que tu salud merece. Control especial de medicamentos regulados.</p>
        </div>
      </div>

      <div class="srv-card rev d3">
        <div class="srv-img">
          <img src="https://images.unsplash.com/photo-1576602976047-174e57a47881?w=600&q=80" alt="Control de Calidad">
          <div class="srv-img-overlay"></div>
          <div class="srv-icon-badge"><i class="bi bi-thermometer-half"></i></div>
        </div>
        <div class="srv-body">
          <h3>Cadena de Frío</h3>
          <p>Control de temperatura en recepción y almacenamiento. Garantizamos la integridad de todos tus medicamentos.</p>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ── PRODUCTOS DESTACADOS ───────────────────────────────── -->
<section id="productos">
  <div class="sec-in">
    <div class="sec-hd rev">
      <div class="sec-tag"><i class="bi bi-stars"></i> Catálogo</div>
      <h2>Productos <em>Destacados</em></h2>
      <p>Selección de medicamentos y productos de salud disponibles en nuestra botica.</p>
    </div>

    <?php if (!empty($productos)): ?>
    <div class="prod-grid">
      <?php foreach ($productos as $i => $p): $d = ($i%4)+1; ?>
      <div class="prod-card rev d<?= $d ?>">
        <div class="prod-img-w">
          <?php if (!empty($p['imagen']) && file_exists("files/articulos/".$p['imagen'])): ?>
            <img src="files/articulos/<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
          <?php else: ?>
            <div class="no-img"><i class="bi bi-capsule-pill" style="font-size:52px;opacity:.22;color:var(--blue)"></i></div>
          <?php endif; ?>
          <div class="prod-avail">Disponible</div>
        </div>
        <div class="prod-body">
          <div class="prod-cat"><?= htmlspecialchars($p['categoria'] ?? 'General') ?></div>
          <div class="prod-name"><?= htmlspecialchars($p['nombre']) ?></div>
          <div class="prod-footer">
            <div class="prod-price">
              S/ <?= number_format($p['precio_venta'], 2) ?>
              <sub>c/u</sub>
            </div>
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

    <div class="ver-mas rev">
      <a href="tienda/index.php" class="btn btn-blue" style="font-size:.95rem;padding:14px 36px">
        <i class="bi bi-grid-fill"></i> Ver Catálogo Completo
      </a>
    </div>
  </div>
</section>

<!-- ── CTA BANNER ─────────────────────────────────────────── -->
<div class="cta-sec">
  <div class="cta-bg"></div>
  <div class="sec-in rev">
    <h2>¿Necesitas orientación sobre tu medicamento?</h2>
    <p>Nuestros farmacéuticos están listos para ayudarte. Visítanos o contáctanos ahora mismo.</p>
    <div class="cta-btns">
      <a href="tienda/index.php" class="btn-cta-w">
        <i class="bi bi-bag-heart-fill"></i> Ir a la Tienda Online
      </a>
      <a href="https://wa.me/<?= $waNro ?>" target="_blank" class="btn-cta-brd">
        <i class="bi bi-whatsapp"></i> Escribir por WhatsApp
      </a>
    </div>
  </div>
</div>

<!-- ── NOSOTROS ───────────────────────────────────────────── -->
<section id="nosotros" class="nosotros">
  <div class="sec-in">
    <div class="nos-grid">
      <div class="nos-img-wrap rev-l">
        <img src="https://images.unsplash.com/photo-1576602976047-174e57a47881?w=800&q=80"
             alt="Interior Botica FarmaSuyana" class="nos-main-img">
        <div class="nos-badge">
          <strong>+5</strong>
          <span>Años al<br>servicio</span>
        </div>
      </div>
      <div class="nos-content rev-r">
        <div class="sec-tag"><i class="bi bi-info-circle-fill"></i> ¿Quiénes Somos?</div>
        <h2>Tu salud es <em>nuestra misión</em></h2>
        <p>
          Botica FarmaSuyana nació con el compromiso de brindar acceso a medicamentos
          de calidad a toda la comunidad de Patibamba Baja y sus alrededores.
          Contamos con farmacéuticos titulados, sistema digital de gestión y
          una plataforma de venta online para servirte mejor.
        </p>
        <div class="nos-feats">
          <div class="nos-feat">
            <div class="nf-ico"><i class="bi bi-patch-check-fill"></i></div>
            <div class="nf-tx">
              <strong>Calidad Certificada</strong>
              <span>Proveedores autorizados por DIGEMID. Trazabilidad completa.</span>
            </div>
          </div>
          <div class="nos-feat">
            <div class="nf-ico"><i class="bi bi-thermometer-half"></i></div>
            <div class="nf-tx">
              <strong>Cadena de Frío Controlada</strong>
              <span>Monitoreo de temperatura en recepción y almacenamiento.</span>
            </div>
          </div>
          <div class="nos-feat">
            <div class="nf-ico"><i class="bi bi-lock-fill"></i></div>
            <div class="nf-tx">
              <strong>Compra 100% Segura</strong>
              <span>Sistema de ventas con comprobantes electrónicos y seguimiento.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CONTACTO ───────────────────────────────────────────── -->
<section id="contacto" class="contacto">
  <div class="sec-in">
    <div class="sec-hd rev">
      <div class="sec-tag"><i class="bi bi-geo-alt-fill"></i> Contáctanos</div>
      <h2>Estamos Aquí para <em>Ayudarte</em></h2>
      <p>Visítanos, llámanos o escríbenos. Siempre hay un farmacéutico listo para atenderte.</p>
    </div>
    <div class="cnt-grid">
      <div class="rev-l">
        <h3>Información de Contacto</h3>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-geo-alt-fill"></i></div>
          <div class="ci-tx">
            <strong>Dirección</strong>
            <span><?= htmlspecialchars($direccion) ?></span>
          </div>
        </div>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-telephone-fill"></i></div>
          <div class="ci-tx">
            <strong>Teléfono / WhatsApp</strong>
            <span><?= htmlspecialchars($telefono) ?></span>
          </div>
        </div>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-envelope-fill"></i></div>
          <div class="ci-tx">
            <strong>Correo Electrónico</strong>
            <span><?= htmlspecialchars($correo) ?></span>
          </div>
        </div>
        <div class="cnt-item">
          <div class="ci-ico"><i class="bi bi-clock-fill"></i></div>
          <div class="ci-tx">
            <strong>Horario de Atención</strong>
            <span>Lunes – Sábado: 8:00 am – 10:00 pm<br>Domingo: 9:00 am – 8:00 pm</span>
          </div>
        </div>
        <a href="https://wa.me/<?= $waNro ?>" target="_blank" class="wa-btn">
          <i class="bi bi-whatsapp" style="font-size:20px"></i>
          Escribir por WhatsApp
        </a>
      </div>
      <div class="rev-r">
        <div class="cnt-map">
          <i class="bi bi-geo-alt-fill"></i>
          <h4>Botica FarmaSuyana</h4>
          <p><?= htmlspecialchars($direccion) ?></p>
          <a href="https://maps.google.com/?q=<?= urlencode($direccion) ?>"
             target="_blank" class="btn btn-blue" style="font-size:.8rem;padding:10px 22px;margin-top:6px">
            <i class="bi bi-map-fill"></i> Abrir en Google Maps
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── FOOTER ─────────────────────────────────────────────── -->
<footer>
  <div class="foot-in">
    <div class="foot-top">
      <div class="foot-brand">
        <img src="files/famacia.png" alt="FarmaSuyana">
        <p>Botica de confianza en Patibamba. Al cuidado de tu salud y de toda tu familia con calidad, responsabilidad y el más alto estándar farmacéutico.</p>
        <div class="foot-social">
          <a href="#" title="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="#" title="Instagram"><i class="bi bi-instagram"></i></a>
          <a href="https://wa.me/<?= $waNro ?>" title="WhatsApp" target="_blank"><i class="bi bi-whatsapp"></i></a>
          <a href="#" title="TikTok"><i class="bi bi-tiktok"></i></a>
        </div>
      </div>
      <div class="foot-col">
        <h5>Navegación</h5>
        <ul>
          <li><a href="#inicio"><i class="bi bi-chevron-right"></i> Inicio</a></li>
          <li><a href="#servicios"><i class="bi bi-chevron-right"></i> Servicios</a></li>
          <li><a href="#productos"><i class="bi bi-chevron-right"></i> Productos</a></li>
          <li><a href="#nosotros"><i class="bi bi-chevron-right"></i> Nosotros</a></li>
          <li><a href="#contacto"><i class="bi bi-chevron-right"></i> Contacto</a></li>
        </ul>
      </div>
      <div class="foot-col">
        <h5>Accesos</h5>
        <ul>
          <li><a href="tienda/index.php"><i class="bi bi-bag-heart-fill"></i> Tienda Online</a></li>
          <li><a href="tienda/login.php"><i class="bi bi-person-circle"></i> Mi Cuenta</a></li>
          <li><a href="vistas/login.html"><i class="bi bi-gear-fill"></i> Sistema Admin</a></li>
          <li><a href="#contacto"><i class="bi bi-headset"></i> Soporte</a></li>
        </ul>
      </div>
      <div class="foot-col">
        <h5>Contacto</h5>
        <ul>
          <li>
            <a href="https://maps.google.com/?q=<?= urlencode($direccion) ?>" target="_blank">
              <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($direccion) ?>
            </a>
          </li>
          <li>
            <a href="tel:<?= $waNro ?>">
              <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($telefono) ?>
            </a>
          </li>
          <li>
            <a href="mailto:<?= htmlspecialchars($correo) ?>">
              <i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($correo) ?>
            </a>
          </li>
          <?php if ($ruc): ?>
          <li><a href="#"><i class="bi bi-card-text"></i> RUC: <?= htmlspecialchars($ruc) ?></a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
    <hr class="foot-hr">
    <div class="foot-bot">
      <p>&copy; <?= date('Y') ?> <a href="#inicio">Botica FarmaSuyana</a> – Al cuidado de tu salud. Todos los derechos reservados.</p>
      <p style="color:#334155;font-size:.78rem">Desarrollado con <i class="bi bi-heart-fill" style="color:var(--red)"></i> para la salud de Patibamba</p>
    </div>
  </div>
</footer>

<!-- WhatsApp flotante -->
<a href="https://wa.me/<?= $waNro ?>" target="_blank" class="wa-fab" title="WhatsApp">
  <i class="bi bi-whatsapp"></i>
</a>

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

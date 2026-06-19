<?php
require_once 'config.php';

// Si ya está autenticado, redirigir a votar
if (isset($_SESSION['elector_id'])) {
    header('Location: votar.php');
    exit;
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $carnet = trim($_POST['carnet'] ?? '');

    if (empty($nombre) || empty($carnet)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                "SELECT id, nombre, ha_votado FROM electores
                 WHERE nombre = :nombre AND carnet = :carnet"
            );
            $stmt->execute([':nombre' => $nombre, ':carnet' => $carnet]);
            $elector = $stmt->fetch();

            if (!$elector) {
                $error = 'Sus datos no están registrados en el padrón electoral. Comuníquese con el Tribunal Electoral.';
            } elseif ($elector['ha_votado']) {
                $error = 'Usted ya ejerció su voto en esta elección. Solo se permite un voto por elector.';
            } else {
                $_SESSION['elector_id'] = $elector['id'];
                $_SESSION['nombre']     = $elector['nombre'];
                $_SESSION['carnet']     = $carnet;
                header('Location: votar.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Error de conexión. Intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elecciones Presidenciales Bolivia 2025</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --rojo:    #C8102E;
            --amarillo:#F4C300;
            --verde:   #007934;
            --oscuro:  #1A1A2E;
            --gris:    #2C2C3E;
            --crema:   #FAF8F3;
            --oro:     #C9A84C;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: var(--oscuro);
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Mapa Bolivia SVG de fondo */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(200,16,46,0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 50%, rgba(0,121,52,0.08) 0%, transparent 60%);
            pointer-events: none;
        }

        /* Estrellas decorativas */
        .estrellas {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .estrella {
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(244,195,0,0.4);
            border-radius: 50%;
            animation: parpadear 3s infinite;
        }
        @keyframes parpadear {
            0%,100% { opacity: 0.2; }
            50%      { opacity: 1; }
        }

        /* Banda tricolor superior */
        .tricolor {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            display: flex;
            z-index: 100;
        }
        .tricolor span { flex: 1; }
        .t-r { background: var(--rojo); }
        .t-a { background: var(--amarillo); }
        .t-v { background: var(--verde); }

        /* Contenedor principal */
        .contenedor {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            padding: 20px;
        }

        /* Header con escudo */
        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .escudo-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 16px;
        }

        /* Escudo SVG de Bolivia estilizado */
        .escudo-svg {
            width: 90px;
            height: 90px;
            filter: drop-shadow(0 0 20px rgba(244,195,0,0.3));
        }

        .pais-label {
            font-family: 'Cinzel', serif;
            font-size: 10px;
            letter-spacing: 4px;
            color: var(--amarillo);
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .titulo-principal {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            font-weight: 700;
            color: white;
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .titulo-anio {
            font-family: 'Cinzel', serif;
            font-size: 36px;
            font-weight: 700;
            color: var(--amarillo);
            letter-spacing: 8px;
            line-height: 1;
            margin-bottom: 6px;
        }

        .subtitulo {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Tarjeta de login */
        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(20px);
            padding: 36px 40px;
            position: relative;
        }

        /* Borde dorado superior */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--rojo), var(--amarillo), var(--verde));
        }

        .card-titulo {
            font-family: 'Cinzel', serif;
            font-size: 13px;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            letter-spacing: 2px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 24px;
        }

        /* Error */
        .error-box {
            background: rgba(200,16,46,0.12);
            border: 1px solid rgba(200,16,46,0.4);
            border-left: 4px solid var(--rojo);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #ffaaaa;
            line-height: 1.5;
        }

        /* Campos */
        .campo {
            margin-bottom: 18px;
        }

        .campo label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin-bottom: 8px;
        }

        .campo input {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }

        .campo input:focus {
            border-color: var(--amarillo);
            background: rgba(255,255,255,0.09);
        }

        .campo input::placeholder { color: rgba(255,255,255,0.25); }

        /* Botón */
        .btn-ingresar {
            width: 100%;
            padding: 15px;
            background: var(--rojo);
            color: white;
            font-family: 'Cinzel', serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 6px;
            position: relative;
            overflow: hidden;
        }

        .btn-ingresar:hover { background: #a00c24; }

        .btn-ingresar::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--amarillo);
        }

        /* Pie */
        .footer-legal {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            color: rgba(255,255,255,0.25);
            line-height: 1.7;
            letter-spacing: 0.5px;
        }

        /* Decoración wiphala */
        .wiphala {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            width: 50px;
            margin: 16px auto;
            opacity: 0.6;
        }
        .wiphala-cell {
            width: 100%;
            aspect-ratio: 1;
        }

        @media (max-width: 500px) {
            .card { padding: 28px 24px; }
            .titulo-anio { font-size: 28px; }
        }
    </style>
</head>
<body>

    <!-- Estrellas de fondo -->
    <div class="estrellas" id="estrellas"></div>

    <!-- Banda tricolor -->
    <div class="tricolor">
        <span class="t-r"></span>
        <span class="t-a"></span>
        <span class="t-v"></span>
    </div>

    <div class="contenedor">

        <!-- Header -->
        <div class="header">
            <!-- Escudo SVG de Bolivia -->
            <svg class="escudo-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <!-- Óvalo verde -->
                <ellipse cx="50" cy="50" rx="42" ry="38" fill="#007934" stroke="#C9A84C" stroke-width="2"/>
                <!-- Montaña nevada -->
                <polygon points="50,20 35,55 65,55" fill="white" opacity="0.9"/>
                <!-- Sol -->
                <circle cx="50" cy="25" r="7" fill="#F4C300"/>
                <!-- Rayos del sol -->
                <g stroke="#F4C300" stroke-width="1.5">
                    <line x1="50" y1="14" x2="50" y2="10"/>
                    <line x1="59" y1="17" x2="62" y2="14"/>
                    <line x1="62" y1="25" x2="66" y2="25"/>
                    <line x1="41" y1="17" x2="38" y2="14"/>
                    <line x1="38" y1="25" x2="34" y2="25"/>
                </g>
                <!-- Llama -->
                <ellipse cx="38" cy="60" rx="5" ry="8" fill="#C8A84C" opacity="0.9"/>
                <ellipse cx="62" cy="60" rx="5" ry="8" fill="#C8A84C" opacity="0.9"/>
                <!-- Cañon y rifles cruzados -->
                <rect x="32" y="68" width="36" height="4" fill="#8B6914" rx="2"/>
                <!-- Franja roja inferior -->
                <ellipse cx="50" cy="88" rx="42" ry="12" fill="#C8102E" opacity="0.8"/>
                <!-- Borde dorado -->
                <ellipse cx="50" cy="50" rx="42" ry="38" fill="none" stroke="#C9A84C" stroke-width="2.5"/>
                <!-- Texto BOLIVIA -->
                <text x="50" y="93" font-family="serif" font-size="7" font-weight="bold"
                      fill="white" text-anchor="middle" letter-spacing="2">BOLIVIA</text>
            </svg>

            <p class="pais-label">Estado Plurinacional de Bolivia</p>
            <h1 class="titulo-principal">Elecciones Presidenciales</h1>
            <div class="titulo-anio">2025</div>
            <p class="subtitulo">Tribunal Supremo Electoral</p>

            <!-- Wiphala decorativa -->
            <div class="wiphala" id="wiphala"></div>
        </div>

        <!-- Tarjeta login -->
        <div class="card">
            <p class="card-titulo">🪪 Verificación del Elector</p>

            <?php if (!empty($error)): ?>
                <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <div class="campo">
                    <label for="nombre">Nombre completo</label>
                    <input type="text" id="nombre" name="nombre"
                           placeholder="Ej: Juan Carlos Mamani Flores"
                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                           required>
                </div>
                <div class="campo">
                    <label for="carnet">Cédula de Identidad</label>
                    <input type="text" id="carnet" name="carnet"
                           placeholder="Ej: 1234567"
                           value="<?= htmlspecialchars($_POST['carnet'] ?? '') ?>"
                           maxlength="20" required>
                </div>
                <button type="submit" class="btn-ingresar">
                    Ingresar al Sistema ▶
                </button>
            </form>
        </div>

        <div class="footer-legal">
            El voto es universal, directo, igual, individual, secreto, libre y obligatorio.<br>
            Art. 26 — Constitución Política del Estado Plurinacional de Bolivia<br>
            <?php echo date('d/m/Y H:i:s'); ?> — Hora oficial Bolivia
        </div>

    </div>

    <script>
        // Generar estrellas de fondo
        const cont = document.getElementById('estrellas');
        for (let i = 0; i < 60; i++) {
            const s = document.createElement('div');
            s.className = 'estrella';
            s.style.left   = Math.random() * 100 + '%';
            s.style.top    = Math.random() * 100 + '%';
            s.style.animationDelay = Math.random() * 3 + 's';
            s.style.animationDuration = (2 + Math.random() * 3) + 's';
            cont.appendChild(s);
        }

        // Wiphala (bandera multicolor andina)
        const colores = [
            '#C8102E','#FF6B00','#F4C300','#007934',
            '#1B3A8B','#6A0DAD','#FFFFFF'
        ];
        const w = document.getElementById('wiphala');
        for (let i = 0; i < 49; i++) {
            const c = document.createElement('div');
            c.className = 'wiphala-cell';
            c.style.background = colores[(i + Math.floor(i/7)) % 7];
            w.appendChild(c);
        }
    </script>

</body>
</html>

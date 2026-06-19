<?php
require_once 'config.php';

// Verificar sesión
if (!isset($_SESSION['elector_id'])) {
    header('Location: index.php');
    exit;
}

$elector_id = $_SESSION['elector_id'];
$nombre     = $_SESSION['nombre'];
$carnet     = $_SESSION['carnet'];
$voto_ok    = false;
$candidato_votado = '';
$error      = '';

// Procesar voto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidato = $_POST['candidato'] ?? '';

    if (!in_array($candidato, ['A', 'B'])) {
        $error = 'Debe seleccionar un candidato para emitir su voto.';
    } else {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            // Doble verificación anti-fraude
            $stmt = $pdo->prepare("SELECT ha_votado FROM electores WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $elector_id]);
            $e = $stmt->fetch();

            if ($e['ha_votado']) {
                $pdo->rollBack();
                session_destroy();
                header('Location: index.php');
                exit;
            }

            // Registrar voto
            $pdo->prepare("INSERT INTO votos (elector_id, candidato) VALUES (:eid, :c)")
                ->execute([':eid' => $elector_id, ':c' => $candidato]);

            // Marcar elector como votado
            $pdo->prepare("UPDATE electores SET ha_votado=1, fecha_voto=NOW() WHERE id=:id")
                ->execute([':id' => $elector_id]);

            // Actualizar conteo
            $pdo->prepare("UPDATE resultados SET total_votos=total_votos+1 WHERE candidato=:c")
                ->execute([':c' => $candidato]);

            $pdo->commit();
            $voto_ok = true;
            $candidato_votado = $candidato;
            session_destroy();

        } catch (Exception $e2) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = 'Error al registrar el voto. Intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emitir Voto — Bolivia 2025</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --rojo:    #C8102E;
            --amarillo:#F4C300;
            --verde:   #007934;
            --oscuro:  #1A1A2E;
            --gris:    #2C2C3E;
            --oro:     #C9A84C;
            --azul-a:  #0D2B6E;
            --cafe-b:  #4A1500;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: var(--oscuro);
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 15% 50%, rgba(200,16,46,0.07) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 50%, rgba(0,121,52,0.07) 0%, transparent 55%);
            pointer-events: none;
        }

        /* Banda tricolor */
        .tricolor {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 6px;
            display: flex;
            z-index: 100;
        }
        .tricolor span { flex: 1; }
        .t-r { background: var(--rojo); }
        .t-a { background: var(--amarillo); }
        .t-v { background: var(--verde); }

        /* Cabecera */
        .cabecera {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 800px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 24px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            margin-bottom: 24px;
            margin-top: 12px;
        }

        .cab-info { display: flex; align-items: center; gap: 14px; }

        .cab-escudo {
            width: 44px;
            height: 44px;
        }

        .cab-titulo {
            font-family: 'Cinzel', serif;
            font-size: 10px;
            color: var(--amarillo);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .cab-sub {
            font-family: 'Cinzel', serif;
            font-size: 14px;
            font-weight: 600;
            color: white;
        }

        .elector-info { text-align: right; }

        .elector-nombre {
            font-size: 13px;
            color: white;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .elector-carnet {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 1px;
        }

        .btn-salir {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            border: 1px solid rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.4);
            font-size: 10px;
            letter-spacing: 1px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-salir:hover { border-color: var(--rojo); color: #ff6666; }

        /* Instrucción */
        .instruccion {
            position: relative;
            z-index: 10;
            text-align: center;
            margin-bottom: 24px;
            width: 100%;
            max-width: 800px;
        }

        .inst-titulo {
            font-family: 'Cinzel', serif;
            font-size: 18px;
            color: white;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .inst-sub {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .linea-oro {
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--oro), transparent);
            margin: 12px auto;
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
            width: 100%;
            max-width: 800px;
            position: relative;
            z-index: 10;
        }

        /* Grid de candidatos */
        .candidatos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            width: 100%;
            max-width: 800px;
            position: relative;
            z-index: 10;
            margin-bottom: 24px;
        }

        @media (max-width: 600px) {
            .candidatos-grid { grid-template-columns: 1fr; }
        }

        /* Tarjeta candidato */
        .candidato-label {
            cursor: pointer;
            display: block;
        }

        .candidato-label input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .candidato-card {
            border: 2px solid rgba(255,255,255,0.08);
            padding: 32px 24px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,0.03);
        }

        .candidato-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* Candidato A — azul */
        .opcion-a .candidato-card::before {
            background: linear-gradient(90deg, #0D2B6E, #1B5EA0);
        }
        /* Candidato B — verde oscuro */
        .opcion-b .candidato-card::before {
            background: linear-gradient(90deg, #007934, #00B34A);
        }

        .candidato-label:hover .candidato-card {
            border-color: rgba(255,255,255,0.2);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.4);
        }

        .candidato-label input:checked + .candidato-card {
            border-color: var(--amarillo);
            box-shadow: 0 0 0 1px var(--amarillo), 0 12px 40px rgba(0,0,0,0.5);
        }

        .candidato-label input:checked + .candidato-card::before {
            opacity: 1;
        }

        /* Inciso letra */
        .inciso {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            font-family: 'Cinzel', serif;
            font-size: 22px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
        }

        .opcion-a .inciso { background: var(--azul-a); border: 2px solid #2A5BA0; }
        .opcion-b .inciso { background: #005A28; border: 2px solid #007934; }

        /* Retrato SVG del candidato */
        .retrato {
            width: 100px;
            height: 100px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 56px;
            border: 3px solid rgba(255,255,255,0.1);
        }

        .opcion-a .retrato { background: radial-gradient(circle, #0D2B6E, #06163A); }
        .opcion-b .retrato { background: radial-gradient(circle, #005A28, #002810); }

        .candidato-partido {
            font-size: 9px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-bottom: 10px;
        }

        .candidato-nombre {
            font-family: 'Cinzel', serif;
            font-size: 17px;
            font-weight: 700;
            color: white;
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .candidato-cargo {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 16px;
        }

        .seleccionado-badge {
            display: none;
            padding: 4px 14px;
            background: var(--amarillo);
            color: #1A1A00;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 0 auto;
        }

        .candidato-label input:checked ~ .candidato-card .seleccionado-badge {
            display: inline-block;
        }

        /* Botón votar */
        .votar-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 800px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .btn-votar {
            padding: 16px 64px;
            background: var(--verde);
            color: white;
            font-family: 'Cinzel', serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }

        .btn-votar:hover { background: #005a26; }

        .btn-votar::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--amarillo);
        }

        .advertencia {
            font-size: 11px;
            color: rgba(255,255,255,0.25);
            margin-top: 10px;
            letter-spacing: 0.5px;
        }

        /* ── PANTALLA DE ÉXITO ── */
        .exito-pantalla {
            position: relative;
            z-index: 10;
            text-align: center;
            max-width: 500px;
            width: 100%;
            padding: 20px;
        }

        .exito-icono {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
            animation: aparecer 0.5s ease;
        }

        @keyframes aparecer {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }

        .exito-titulo {
            font-family: 'Cinzel', serif;
            font-size: 26px;
            color: white;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .exito-sub {
            font-size: 14px;
            color: rgba(255,255,255,0.5);
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .exito-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-left: 4px solid var(--verde);
            padding: 20px 28px;
            margin-bottom: 28px;
            text-align: left;
        }

        .exito-card-label {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-bottom: 6px;
        }

        .exito-card-nombre {
            font-family: 'Cinzel', serif;
            font-size: 17px;
            color: white;
            font-weight: 600;
        }

        .btn-inicio {
            display: inline-block;
            padding: 14px 40px;
            background: var(--rojo);
            color: white;
            font-family: 'Cinzel', serif;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-inicio:hover { background: #a00c24; }

        .exito-nota {
            margin-top: 16px;
            font-size: 10px;
            color: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>

    <div class="tricolor">
        <span class="t-r"></span><span class="t-a"></span><span class="t-v"></span>
    </div>

<?php if ($voto_ok): ?>
    <!-- ════════ PANTALLA DE ÉXITO ════════ -->
    <div class="cabecera" style="justify-content:center;">
        <div style="text-align:center;">
            <div style="font-family:'Cinzel',serif;font-size:10px;color:var(--amarillo);letter-spacing:2px;">
                Tribunal Supremo Electoral — Bolivia 2025
            </div>
            <div style="font-family:'Cinzel',serif;font-size:14px;color:white;font-weight:600;">
                Sistema de Votación Presidencial
            </div>
        </div>
    </div>

    <div class="exito-pantalla">
        <span class="exito-icono">✅</span>
        <h2 class="exito-titulo">¡Voto Registrado!</h2>
        <p class="exito-sub">
            Su voto ha sido emitido de forma segura y confidencial.<br>
            Gracias por ejercer su derecho democrático.
        </p>

        <div class="exito-card">
            <p class="exito-card-label">
                Votó por la opción <?= htmlspecialchars($candidato_votado) ?>
            </p>
            <p class="exito-card-nombre">
                <?= $candidato_votado === 'A' ? 'Víctor Paz Estenssoro' : 'Luis Arce Catacora' ?>
            </p>
        </div>

        <a href="index.php" class="btn-inicio">← Volver al Inicio</a>
        <p class="exito-nota">Su sesión fue cerrada. El voto es secreto e irrevocable.</p>
    </div>

<?php else: ?>
    <!-- ════════ CABECERA ════════ -->
    <div class="cabecera">
        <div class="cab-info">
            <svg class="cab-escudo" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="50" cy="50" rx="42" ry="38" fill="#007934" stroke="#C9A84C" stroke-width="2"/>
                <polygon points="50,20 35,55 65,55" fill="white" opacity="0.9"/>
                <circle cx="50" cy="25" r="7" fill="#F4C300"/>
                <ellipse cx="50" cy="88" rx="42" ry="12" fill="#C8102E" opacity="0.8"/>
                <text x="50" y="93" font-family="serif" font-size="7" font-weight="bold"
                      fill="white" text-anchor="middle" letter-spacing="2">BOLIVIA</text>
                <ellipse cx="50" cy="50" rx="42" ry="38" fill="none" stroke="#C9A84C" stroke-width="2.5"/>
            </svg>
            <div>
                <div class="cab-titulo">Tribunal Supremo Electoral</div>
                <div class="cab-sub">Elecciones Presidenciales Bolivia 2025</div>
            </div>
        </div>
        <div class="elector-info">
            <div class="elector-nombre">👤 <?= htmlspecialchars($nombre) ?></div>
            <div class="elector-carnet">CI: <?= htmlspecialchars($carnet) ?></div>
            <a href="logout.php" class="btn-salir">Cancelar ✕</a>
        </div>
    </div>

    <!-- Instrucción -->
    <div class="instruccion">
        <h2 class="inst-titulo">Seleccione su Candidato</h2>
        <div class="linea-oro"></div>
        <p class="inst-sub">Elija una opción y confirme su voto</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Papeleta -->
    <form method="POST" action="votar.php" id="form-voto">
        <div class="candidatos-grid">

            <!-- CANDIDATO A -->
            <label class="candidato-label opcion-a">
                <input type="radio" name="candidato" value="A" required>
                <div class="candidato-card">
                    <div class="inciso">A</div>
                    <div class="retrato">👨‍💼</div>
                    <p class="candidato-partido">Movimiento Nacionalista Revolucionario</p>
                    <h3 class="candidato-nombre">Víctor Paz<br>Estenssoro</h3>
                    <p class="candidato-cargo">Candidato a Presidente de Bolivia</p>
                    <div class="seleccionado-badge">✔ Seleccionado</div>
                </div>
            </label>

            <!-- CANDIDATO B -->
            <label class="candidato-label opcion-b">
                <input type="radio" name="candidato" value="B" required>
                <div class="candidato-card">
                    <div class="inciso">B</div>
                    <div class="retrato">🎖️</div>
                    <p class="candidato-partido">Movimiento Al Socialismo</p>
                    <h3 class="candidato-nombre">Luis Arce<br>Catacora</h3>
                    <p class="candidato-cargo">Candidato a Presidente de Bolivia</p>
                    <div class="seleccionado-badge">✔ Seleccionado</div>
                </div>
            </label>

        </div>

        <div class="votar-wrapper">
            <button type="submit" class="btn-votar" onclick="return confirmar()">
                🗳️ &nbsp;Emitir Voto
            </button>
            <p class="advertencia">Esta acción es irreversible. Un solo voto por elector.</p>
        </div>
    </form>

<?php endif; ?>

    <script>
        function confirmar() {
            const sel = document.querySelector('input[name="candidato"]:checked');
            if (!sel) {
                alert('⚠️ Debe seleccionar un candidato antes de emitir su voto.');
                return false;
            }
            const nombres = {
                'A': 'Víctor Paz Estenssoro',
                'B': 'Luis Arce Catacora'
            };
            return confirm(
                '¿Confirma su voto por:\n\n' +
                'Opción ' + sel.value + ': ' + nombres[sel.value] + '\n\n' +
                'Esta acción NO se puede deshacer.'
            );
        }
    </script>

</body>
</html>

<?php
// Política de privacidad (zona pública).
// Es solo contenido. Sigue la idea de la Ley 29733 (Protección de Datos
// Personales, Perú) en lenguaje sencillo. La clínica es FICTICIA: este
// texto es un ejemplo académico, no un documento legal real.
$titulo_pagina = 'Política de privacidad';
require __DIR__ . '/../app/layout/publico_header.php';
?>

<section class="seccion texto-legal">
    <p class="etiqueta">Privacidad</p>
    <h1>Política de privacidad</h1>
    <p class="portada-bajada">
        Tus datos de salud son tuyos. Aquí te contamos, sin letra pequeña,
        qué guardamos, para qué y quién puede verlo.
    </p>

    <p class="aviso aviso-pendiente">
        Clínica Vida Sana es una <strong>clínica simulada</strong> de un proyecto
        académico. Este texto es un ejemplo y no tiene valor legal.
    </p>

    <h2>1. Quién cuida tus datos</h2>
    <p>
        Clínica Vida Sana es la responsable de tus datos personales. Para cualquier
        consulta sobre ellos puedes acercarte a <strong>admisión</strong> o escribir
        desde la página de <a href="<?= e(url('/contacto.php')) ?>">contacto</a>.
    </p>

    <h2>2. Qué datos guardamos</h2>
    <ul>
        <li><strong>De identificación:</strong> nombres, apellidos, tipo y número de documento.</li>
        <li><strong>De contacto:</strong> correo y celular.</li>
        <li><strong>De tu cuenta:</strong> tu contraseña, guardada de forma cifrada (nadie, ni siquiera la clínica, puede leerla).</li>
        <li><strong>De salud:</strong> tus citas y tu historia clínica (diagnósticos, tratamientos y notas de tus médicos). Son <strong>datos sensibles</strong> y reciben la mayor protección.</li>
    </ul>

    <h2>3. Para qué los usamos</h2>
    <p>
        Solo para <strong>atenderte</strong>: identificarte, programar tus citas,
        que tu médico conozca tus antecedentes y que tú puedas consultar tu historia.
        <strong>No vendemos ni compartimos tus datos</strong> con terceros para publicidad.
    </p>

    <h2>4. Quién puede verlos</h2>
    <ul>
        <li><strong>Tú</strong>, desde el Portal del paciente, una vez verificada tu cuenta.</li>
        <li><strong>Admisión</strong>: tus datos de contacto y tus citas, para organizarlas. No ve tu historia clínica.</li>
        <li><strong>Tu médico</strong>: tus datos básicos y la atención de las citas que tiene contigo.</li>
    </ul>
    <p>
        <strong>Cada consulta a tu historia queda registrada</strong> (quién, cuándo y
        desde dónde). Puedes ver quién la consultó en tu propia historia clínica,
        dentro del portal.
    </p>

    <h2>5. Cómo los protegemos</h2>
    <ul>
        <li>La web funciona con conexión cifrada (HTTPS).</li>
        <li>Las contraseñas se guardan cifradas y hay un límite de intentos para entrar.</li>
        <li>Cada persona del personal solo ve lo que necesita para su trabajo.</li>
        <li>Llevamos un registro de auditoría de los accesos a datos clínicos.</li>
    </ul>

    <h2>6. Tus derechos</h2>
    <p>
        Puedes pedir en admisión, con tu documento de identidad:
    </p>
    <ul>
        <li><strong>Acceso:</strong> saber qué datos tuyos tenemos.</li>
        <li><strong>Rectificación:</strong> corregir un dato que esté mal.</li>
        <li><strong>Cancelación:</strong> dar de baja tu cuenta del portal. Tu historia clínica se conserva el tiempo que exigen las normas de salud.</li>
        <li><strong>Oposición:</strong> negarte a un uso de tus datos que no sea tu atención.</li>
    </ul>
    <p>Tu contraseña la puedes cambiar tú mismo en cualquier momento desde "Mi perfil".</p>

    <h2>7. Tu consentimiento</h2>
    <p>
        Al crear tu cuenta marcas la casilla de aceptación y guardamos la
        <strong>fecha y hora</strong> en que lo hiciste. Sin esa aceptación no
        podemos crear tu cuenta en el portal (puedes atenderte igual en admisión).
    </p>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>

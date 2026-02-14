<?php
session_start();

/**
 * Llama a Ollama y devuelve la respuesta
 */
function ask_ollama(string $userPrompt): string {

  $prompt = "
Pregunta del usuario:
{$userPrompt}

Responde en español (España).
Habla SOLO sobre animales (mascotas, fauna, cuidados, comportamiento).
Responde en un solo párrafo, sin código, de forma clara y sencilla.

Si la pregunta NO es sobre animales, responde exactamente:
Solo respondo preguntas sobre animales.
";

  $data = [
    "model"  => "llama3:latest",
    "prompt" => $prompt,
    "stream" => false,
    "options" => [
      "temperature" => 0.6,
      "num_predict" => 400
    ]
  ];

  $ch = curl_init("http://127.0.0.1:11434/api/generate");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 30,
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  $json = json_decode($response ?? "", true);
  $out  = trim((string)($json["response"] ?? ""));

  if ($out === "") {
    return "No he podido generar una respuesta, prueba a reformular la pregunta.";
  }

  return $out;
}

$pregunta = null;
$respuesta = null;
$showSpinner = false;
$metaRefresh = "";

/* STEP A: envío del formulario */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["prompt"])) {
  $pregunta = trim($_POST["prompt"]);

  $_SESSION["last_prompt"]  = $pregunta;
  $_SESSION["last_answer"]  = "";
  $_SESSION["answer_ready"] = false;

  $showSpinner = true;
  $metaRefresh = '<meta http-equiv="refresh" content="1.0;url=?step=answer">';
}

/* STEP B: mostrar respuesta */
if (isset($_GET["step"]) && $_GET["step"] === "answer") {
  $pregunta = $_SESSION["last_prompt"] ?? null;

  if (!empty($_SESSION["answer_ready"])) {
    $respuesta = $_SESSION["last_answer"] ?? "";
    $showSpinner = false;
  } else {
    $showSpinner = true;
    $metaRefresh = '<meta http-equiv="refresh" content="1.0;url=?step=answer">';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>microchat animales</title>
<?= $metaRefresh ?>
<style>
html,body{
  width:100%;height:100%;margin:0;
  font-family:Ubuntu,sans-serif;
  background:#ddd;
}
body{display:flex;align-items:center;justify-content:center;}
section{
  width:520px;height:560px;
  background:#fff;border-radius:30px;
  padding:20px;
  box-shadow:0 20px 40px rgba(0,0,0,.2);
  display:flex;flex-direction:column;
}
h1{text-align:center;margin:0 0 10px;}
.messages{
  flex:1;display:flex;flex-direction:column;gap:12px;
}
.messages.empty{justify-content:center;align-items:center;}
.welcome{color:#777;font-size:14px;}
.bubble{
  max-width:80%;padding:14px;background:#eee;
  border-radius:15px;font-size:14px;
}
#pregunta{align-self:flex-start;border-top-left-radius:5px;}
#respuesta{
  align-self:flex-end;border-top-right-radius:5px;
  display:flex;gap:10px;align-items:center;
}
form{
  border-top:1px solid #ddd;padding-top:12px;
  display:flex;justify-content:center;
}
form input{
  width:90%;max-width:460px;
  padding:12px;border-radius:30px;
  border:1px solid #ccc;outline:none;
}
.spinner{
  width:16px;height:16px;border:3px solid #bbb;
  border-top-color:#555;border-radius:50%;
  animation:spin .8s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
.muted{color:#777;font-size:13px;}
</style>
</head>
<body>

<section>
  <h1>🐾 microchat animales</h1>

  <div class="messages <?= !$pregunta ? 'empty' : '' ?>">
    <?php if (!$pregunta): ?>
      <div class="welcome">Pregunta algo sobre animales</div>
    <?php else: ?>
      <p id="pregunta" class="bubble"><?= htmlspecialchars($pregunta) ?></p>
      <p id="respuesta" class="bubble">
        <?php if ($showSpinner): ?>
          <span class="spinner"></span>
          <span class="muted">Pensando…</span>
        <?php else: ?>
          <?= htmlspecialchars($respuesta) ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>

  <form method="POST">
    <input
      type="text"
      name="prompt"
      placeholder="Pregunta algo sobre animales y pulsa Enter…"
      autofocus
    >
  </form>
</section>

</body>
</html>
<?php
/* Trabajo pesado tras renderizar (spinner visible) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["prompt"])) {
  session_write_close();              // liberar sesión
  $ans = ask_ollama($pregunta ?? "");
  session_start();                    // reabrir sesión
  $_SESSION["last_answer"]  = $ans;
  $_SESSION["answer_ready"] = true;
  session_write_close();
}


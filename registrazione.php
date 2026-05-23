<?php $nome = "";
$email = "";
$password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST["nome"];
    $email = $_POST["email"];
    $password = $_POST["password"];
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registrazione</title>
</head>
<body>

    <form method="POST" action="">
        <p>
            <input type="text" name="nome" placeholder="Inserire nome e cognome" value="<?php echo $nome; ?>">
        </p>
        <p>
            <input type="email" name="email" placeholder="Inserire email" value="<?php echo $email; ?>">
        </p>
        <p>
            <input type="password" name="password" placeholder="Inserire password">
        </p>
        <p>
            <button type="submit">Registrati</button>
        </p>
    </form>

</body>
</html>
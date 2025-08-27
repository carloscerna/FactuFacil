<?php
// ruta de los archivos con su carpeta
$path_root=trim($_SERVER['DOCUMENT_ROOT']);
//Load Composer's autoloader
require "$path_root/FactuFacil/vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'carlos.wilfredo.cerna@clases.edu.sv';
    $mail->Password = 'wvzlurlrerzcqzju';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('carlos.wilfredo.cerna@clases.edu.sv', 'COED 10391');
    $mail->addAddress('carlos.w.cerna@gmail.com', 'Carlos Cerna');

    $mail->isHTML(true);
    $mail->Subject = 'Pruba de correo';
    $mail->Body = 'Este es el <b>contenido</b> del correo.';
    $mail->AltBody = 'Este es el contenido del correo en texto plano.';
    
    // Adjuntar el archivo
    $rutaArchivo = '/TempSistemaRegistro/Carpetas/10391/Boleta Calificaciones/Educacion_Basica/2024/Cuarto A/ALFARO MEJÍA, CARLOS MAURICIO.pdf'; // Cambia esta ruta
    $mail->addAttachment($rutaArchivo);
    // Construir el cuerpo del correo
    $nombre = "Registro Académico";
    $rutaImagen = "$path_root/FactuFacil/img/logo_cerz.png"; // Ruta de la imagen
    $mail->Body = "
        <p>Hola,</p>
        <p>Boleta de Calificaciones.</p>
        <br>
        <p>Atentamente,</p>
        <p><b>{$nombre}</b></p>
        <img src='cid:miImagen' width='100' height='100'>
    ";

    // Adjuntar la imagen como "inline" (en el cuerpo del correo)
    $mail->addEmbeddedImage($rutaImagen, 'miImagen');
    //
    $mail->send();
    echo 'Correo enviado exitosamente.';
} catch (Exception $e) {
    echo "Error al enviar el correo: {$mail->ErrorInfo}";
}
<!-- resources/views/emails/stock_report.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Stock Crítico</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            color: #2c5282;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }
        p {
            margin-bottom: 15px;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #718096;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reporte de Stock Crítico</h1>

        <p>Estimado usuario,</p>

        <p>Adjunto encontrará el reporte de Stock Crítico actualizado al {{ date('d/m/Y H:i') }}.</p>

        <p>Este reporte contiene los productos que actualmente tienen un stock igual o menor a 5 unidades.</p>

        <p>Recomendamos revisar este listado y tomar las acciones necesarias para reabastecer los productos con bajo stock.</p>

        <p>Saludos cordiales,</p>

        <p><strong>Sistema de Reportes ML</strong></p>

        <div class="footer">
            <p>Este es un correo automático, por favor no responda a este mensaje.</p>
        </div>
    </div>
</body>
</html>

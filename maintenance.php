<?php
$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : '';
if (!in_array($protocol, array('HTTP/1.1', 'HTTP/2', 'HTTP/2.0'), true))
{
    $protocol = 'HTTP/1.0';
}
header("$protocol 503 Service Unavailable", true, 503);
header('Content-Type: text/html; charset=utf-8');
header('Retry-After: 30');
?>

<!doctype html>
<html lang="fr">
    <head>
        <title>Site Maintenance</title>
        <meta charset="utf-8">
        <meta name="robots" content="noindex">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                text-align: center;
                padding: 20px;
                font: 20px Helvetica, sans-serif;
                color: #333;
                background-color:#FFFFFF
            }
            @media (min-width: 768px){
                body{
                    padding-top: 150px;
                }
            }
            h1 {
                font-size: 50px;
            }
            article {
                display: block;
                text-align: left;
                max-width: 650px;
                margin: 0 auto;
            }
            a {
                color: #dc8100;
                text-decoration: none;
            }
            a:hover {
                color: #333;
                text-decoration: none;
            }
            svg {
                width: 75px;
                margin-top: 1em;
            }
        </style>
    </head>
    <body>
        <article> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 202.24 202.24"><defs><style>.cls-1{
                    fill:#444;
                }</style></defs><title>Asset 3</title><g id="Layer_2" data-name="Layer 2"><g id="Capa_1" data-name="Capa 1"><path class="cls-1" d="M101.12,0A101.12,101.12,0,1,0,202.24,101.12,101.12,101.12,0,0,0,101.12,0ZM159,148.76H43.28a11.57,11.57,0,0,1-10-17.34L91.09,31.16a11.57,11.57,0,0,1,20.06,0L169,131.43a11.57,11.57,0,0,1-10,17.34Z"/><path class="cls-1" d="M101.12,36.93h0L43.27,137.21H159L101.13,36.94Zm0,88.7a7.71,7.71,0,1,1,7.71-7.71A7.71,7.71,0,0,1,101.12,125.63Zm7.71-50.13a7.56,7.56,0,0,1-.11,1.3l-3.8,22.49a3.86,3.86,0,0,1-7.61,0l-3.8-22.49a8,8,0,0,1-.11-1.3,7.71,7.71,0,1,1,15.43,0Z"/></g></g></svg>
            <h1>Maintenance</h1>
            <div>
                <p>Le site est actuellement indisponible car j'effectue des travaux de maintenance. En cas de besoin, vous pouvez toujours <a href="mailto:info@ladecadanse.ch">me contacter</a>, sinon le site sera de nouveau en ligne sous peu !</p>
                <p>&mdash; MG</p>

            </div>
            <div style="display: flex; flex-direction: row; justify-content: space-between;">
                <p class="day"></p>
                <p class="hour"></p>
                <p class="minute"></p>
                <p class="second"></p>
            </div>
        </article>
        <script>
//            const countDown = () => {
//                const countDay = new Date('December 28, 2023 00:00:00');
//                const now = new Date();
//                const counter = countDay - now;
//                const second = 1000;
//                const minute = second * 60;
//                const hour = minute * 60;
//                const day = hour * 24;
//                const textDay = Math.floor(counter / day);
//                const textHour = Math.floor((counter % day) / hour);
//                const textMinute = Math.floor((counter % hour) / minute);
//                const textSecond = Math.floor((counter % minute) / second)
//                //document.querySelector(".day").innerText = textDay + ' Days';
//                document.querySelector(".hour").innerText = textHour + ' heure';
//                document.querySelector(".minute").innerText = textMinute + ' minutes';
//                document.querySelector(".second").innerText = textSecond + ' secondes';
//            }
//            countDown();
//            setInterval(countDown, 1000);
        </script>
    </body>
</html>
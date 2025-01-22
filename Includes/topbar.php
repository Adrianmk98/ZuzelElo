<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background-image: url('Includes/background.jpg'); /* Path to your image */
            background-size: cover; /* Ensures the image covers the full page */
            background-position: center; /* Centers the image */
            background-attachment: fixed; /* Fixes the background when scrolling */
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw; /* Ensures full width */
            height: 100vh; /* Ensures full height */
            z-index: -1; /* Ensures the overlay is behind other content */

        }

        .topnav {
            overflow: hidden;
            background-color: #333;
            position: fixed; /* Fixes the topnav to the top */
            top: 0;
            left: 0;
            width: 100%; /* Ensures it spans the full width */
            z-index: 1000; /* Ensures it's above other elements */
        }

        .topnav a {
            float: left;
            color: #f2f2f2;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
            font-size: 17px;
        }

        .topnav a:hover {
            background-color: #ddd;
            color: black;
        }

        .topnav a.active {
            background-color: #4CAF50;
            color: white;
        }
    </style>
</head>
<body>
<div class="topnav">
    <a class="active" href="main.php">Home</a>
    <a href="matches.php">Matches</a>
    <a href="elochecker.php">Race Simulator</a>
    <a href="freeAgents.php">Free Agents</a>
    <a href="leagueplayers.php">Teams</a>
    <a href="tabela.php">Standings</a>
    <a href="#">Logout</a>
</div>
</body>
<br><br>
</html>
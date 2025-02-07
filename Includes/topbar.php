<meta name="viewport" content="width=device-width, initial-scale=1">

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

    .topnav {
        overflow: hidden;
        background-color: #333;
    }

    .topnav a {
        float: left;
        color: #f2f2f2;
        text-align: center;
        padding: 14px 16px;
        text-decoration: none;
        font-size: 17px;
    }

    .topnav a:hover, .dropdown:hover .dropbtn {
        background-color: #ddd;
        color: black;
    }

    .topnav a.active {
        background-color: #4CAF50;
        color: white;
    }



    .navbar a {
        float: left;
        display: block;
        color: #f2f2f2;
        text-align: center;
        padding: 14px 16px;
        text-decoration: none;
        font-size: 17px;
    }

    .navbar a.active {
        background-color: #4CAF50;
        color: white;
    }

    .dropdown {
        float: left;
        overflow: hidden;
    }

    .dropdown .dropbtn {
        font-size: 17px;
        border: none;
        outline: none;
        color: white;
        padding: 14px 16px;
        background-color: inherit;
        font-family: inherit;
        margin: 0;
        text-align: center;
    }
    .dropdown-content {
        display: none;
        position: absolute;
        background-color: #f9f9f9;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1;
    }
    .dropdown-content a {
        float: none;
        color: black;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        text-align: left;
    }

    .dropdown-content a:hover {
        background-color: #ddd;
    }

    .dropdown:hover .dropdown-content {
        display: block;

    }


</style>
<body>
<div class="topnav">
    <a class="active" href="main.php">Home</a>
    <a href="matches.php">Matches</a>

    <div class="dropdown">
        <button class="dropbtn">Simulation
            <i class="fa fa-caret-down"></i>
        </button>
        <div class="dropdown-content">
            <a href="matchSimulator.php" >Match Simulator</a>
            <a href="raceSimulator.php" >Race Simulator</a>

        </div>
    </div>
    <a href="freeAgents.php">Free Agents</a>


    <a href="teams.php">Teams</a>
    <a href="tabela.php">Standings</a>
    <a href="record.php">Record</a>
    <a href="math.php">Math</a>

    </a>
</div>

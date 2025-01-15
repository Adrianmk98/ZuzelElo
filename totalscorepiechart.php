<html>
<head>

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawChart);

        function drawChart() {

            var data = google.visualization.arrayToDataTable([
                ['Task', 'Hours per Day'],
                ['<?php echo $match_details['awayTeamName'];?>',     <?php echo htmlspecialchars($totalawayScore);?>],
                ['<?php echo $match_details['homeTeamName'];?>',      <?php echo htmlspecialchars($totalhomeScore);?>],

            ]);

            var options = {
                legend: 'none'
            };

            var chart = new google.visualization.PieChart(document.getElementById('piechart'));

            chart.draw(data, options);
        }
    </script>
</head>
<body>
<div id="piechart" style="width: 200px; height: 100px;"></div>
</body>
</html>
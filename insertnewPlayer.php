<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database connection variables
    $servername = "localhost";  // Your database server
    $dbusername = "root";
    $dbpassword = "";
    $dbname = "zuzelelo";
    $year=2025;

    // Create connection
    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Retrieve data from text boxes
    $firstName = $_POST["input1"];
    $lastName = $_POST["input2"];
    $yearofbirth = $_POST["input3"];
    $teamID = $_POST["input4"];

    if (isset($_POST["insert"])) {
        // Insert values into the database
        $sql = "INSERT INTO player (FirstName, LastName, YoB,teamID) VALUES (?, ?, ?,?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssii", $firstName, $lastName, $yearofbirth,$teamID);
            if ($stmt->execute()) {
                echo "Data successfully inserted!";
            } else {
                echo "Error executing statement: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $conn->error;
        }
    } elseif (isset($_POST["calculate"])) {
        // Perform a calculation with input3
        $calculatedValue = $value3 * 2; // Example: Double the value of input3
        echo "Calculated value based on input3: $calculatedValue";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert or Calculate</title>
</head>
<body>
<h1>Insert Data or Perform Calculation</h1>
<form method="post">
    <label for="input1">First Name:</label>
    <input type="text" id="input1" name="input1" required>
    <br><br>
    <label for="input2">Last Name:</label>
    <input type="text" id="input2" name="input2" required>
    <br><br>
    <label for="input3">YoB:</label>
    <input type="text" id="input3" name="input3" required>
    <br><br>
    <label for="input4">teamID:</label>
    <input type="text" id="input4" name="input4" required>
    <br><br>
    <button type="submit" name="insert">Insert into Database</button>
</form>
</body>
</html>

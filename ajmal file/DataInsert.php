<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Call the function to insert data
    insertData($_POST);
}
ajmal;

// Function to insert data into the database
function insertData($data)
{
    // Database credentials
    $host = 'localhost'; // Replace with your database host
    $dbname = 'jenkin'; // Replace with your database name
    $username = 'root'; // Replace with your database username
    $password = '1234'; // Replace with your database password

    try {
        // Create PDO connection
        $conn = new PDO("mysql:host=$host;dbname=$dbname;port=3306", $username, $password);

        // Set PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepare the SQL INSERT query
        $sql = "INSERT INTO jenkinresponse (
            job_id, branch_name, product_name, job_name, repository_name, file_name,
            file_status, server_ip, server_name, server_file_path, commit_id, revision_number,
            commit_message, job_status, started_by, approved_by, started_datetime, approved_datetime
        ) VALUES (
            :job_id, :branch_name, :product_name, :job_name, :repository, :file_name,
            :file_status, :server_ip, 'AD_DEV_SERVER', :server_file_path, :commit_id, :revision_number,
            :commit_message, :job_status, :started_by, :approved_by, DATE_SUB(NOW(), INTERVAL 1 HOUR), now()
        )";

        // Prepare the statement
        $stmt = $conn->prepare($sql);

        // Bind values
        $stmt->bindValue(':job_id', 1520); // Default value
        $stmt->bindValue(':branch_name', $data['branch_name']); // From dropdown
        $stmt->bindValue(':product_name', 'AgencyDirect_MHGOMCORP'); // Default value
        $stmt->bindValue(':job_name', 'AgencyDirect/' . $data['branch_name']); // Derived value
        $stmt->bindValue(':repository', 'AgencyDirect'); // Default value
        $stmt->bindValue(':file_name', $data['file_name']); // User input
        $stmt->bindValue(':file_status', 'M'); // Default value
        $stmt->bindValue(':server_ip', '15.206.193.68'); // Default value
        $stmt->bindValue(':server_file_path', '/home/Staging/Corporate'); // Default value
        $stmt->bindValue(':commit_id', $data['commit_id']); // User input
        $stmt->bindValue(':revision_number', null); // Default value
        $stmt->bindValue(':commit_message', $data['commit_message']); // User input
        $stmt->bindValue(':job_status', 'SUCCESS'); // Default value
        $stmt->bindValue(':started_by', 'Jagannathan M'); // Default value
        $stmt->bindValue(':approved_by', 'jagannathanm'); // Default value
        // $stmt->bindValue(':started_datetime', DATE_SUB(NOW(), INTERVAL 1 HOUR)); // Default value
        // $stmt->bindValue(':approved_datetime', now()); // Default value

        // Execute the query
        $stmt->execute();

        echo "Data inserted successfully!";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Jenkin Response</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h2 class="text-center">Insert Jenkin Response</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="branch_name">Branch Name</label>
                <select class="form-control" id="branch_name" name="branch_name" required>
                    <option value="dev">Dev</option>
                    <option value="test">Test</option>
                    <option value="uat">UAT</option>
                    <option value="prelive">Prelive</option>
                    <option value="live">Live</option>
                </select>
            </div>
            <div class="form-group">
                <label for="file_name">File Name</label>
                <input type="text" class="form-control" id="file_name" name="file_name" placeholder="Enter file name"
                    required>
            </div>
            <div class="form-group">
                <label for="commit_id">Commit ID</label>
                <input type="text" class="form-control" id="commit_id" name="commit_id" placeholder="Enter commit ID"
                    required>
            </div>
            <div class="form-group">
                <label for="commit_message">Commit Message</label>
                <textarea class="form-control" id="commit_message" name="commit_message"
                    placeholder="Enter commit message" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Insert Data</button>
        </form>
    </div>
</body>

</html>
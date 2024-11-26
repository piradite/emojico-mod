<?php
// friends_list.php

// Database connection
$host = "localhost";
$dbname = "emojmkpb_users";
$user = "emojmkpb_admin";
$db_password = "HDWcuUtD-sU'GV4";


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit();
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Please log in to view your friends list.";
    exit();
}

$loggedInUserId = $_SESSION['user_id'];

// Fetch list of friends with accepted friend requests
$query = "
    SELECT u.username, u.profile_picture
    FROM users u
    JOIN friend_requests fr ON (fr.sender_id = u.id OR fr.receiver_id = u.id)
    WHERE 
        (fr.sender_id = :user_id OR fr.receiver_id = :user_id)
        AND fr.status = 'accepted'
        AND u.id != :user_id
";
$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $loggedInUserId]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch the logged-in user's username
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userData) {
    $username = htmlspecialchars($userData['username']);
} else {
    // Handle the case where the user is not found
    echo 'User not found. Please log in again. <a href="https://emojico.net/login" target="_blank">Login</a>';
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Friends List</title>
    <link rel="stylesheet" href="css/friends_list.css">
    <style>

        
    </style>
</head>
<body>
    
    
<div class="hamburger-menu">
    <div class="menu-icon" onclick="toggleMenu()"> <!-- Hamburger Icon -->
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="dropdown-menu" id="dropdownMenu">
        <a href="https://emojico.net" target="_blank">Home</a>
        <a href="profile.php?user=<?php echo htmlspecialchars($username); ?>">Profile</a>
         <a href="https://emojico.net/login/account.php">Dashboard</a> 
         <a href="https://emojico.net/discover.php">Explore</a>
    </div>
</div>

    <h1>Your Friends</h1>
    <div class="friends-list-container">
        <?php if (count($friends) > 0): ?>
            <?php foreach ($friends as $friend): ?>
                <div class="friend-item">
                    <img src="<?php echo htmlspecialchars($friend['profile_picture'] ?: 'https://emojico.net/menutesting/images/account.png'); ?>" alt="Profile Picture">
                    <a href="profile.php?user=<?php echo htmlspecialchars($friend['username']); ?>">
                        <?php echo htmlspecialchars($friend['username']); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="no-friends-message">You have no friends to display.</p>
        <?php endif; ?>
    </div> 
    
      <!-- Discover Icon -->
    <a href="https://emojico.net/discover.php" target="_blank">
        <div class="discover-icon"></div>
    </a>
</body>
</html>

</body>
<script>
    
    function toggleMenu() {
    const menu = document.getElementById('dropdownMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

// Close menu when clicking outside of it
document.addEventListener('click', function(event) {
    const menu = document.getElementById('dropdownMenu');
    const icon = document.querySelector('.menu-icon');
    if (!menu.contains(event.target) && !icon.contains(event.target)) {
        menu.style.display = 'none';
    }
});

    
    
    
    
</script>
</html>

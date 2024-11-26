<?php

session_start();
$loggedInUserId = $_SESSION["user_id"] ?? null; // Null if not logged in
$loggedInUserId = $_SESSION["user_id"]; // Store the logged-in user's ID

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

// Fetch pending friend requests for the logged-in user
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS pending_requests FROM friend_requests WHERE receiver_id = :id AND status = 'pending'"
);
$stmt->bindParam(":id", $_SESSION["user_id"], PDO::PARAM_INT);
$stmt->execute();
$friendRequestCount = $stmt->fetchColumn();

if (!isset($_GET["user"])) {
    echo "Profile not found.";
    exit();
}

$username = htmlspecialchars($_GET["user"]);

$stmt = $pdo->prepare(
    "SELECT * FROM users WHERE username = :username AND is_public = 1"
);
$stmt->bindParam(":username", $username, PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "This profile is private or does not exist.";
    exit();
}

// Check if viewing one's own profile
$isOwnProfile = $user["id"] == $loggedInUserId;

// Fetch the color values from the database
$formColor = htmlspecialchars($user["form_color"]); // Ensure this is in the database
$secondaryColor = htmlspecialchars($user["secondary_color"]); // Ensure this is in the database

// Fetch friends for the logged-in user (friends where status is 'accepted')
if ($isOwnProfile) {
    $stmt = $pdo->prepare("SELECT u.username, u.profile_picture 
                           FROM users u
                           JOIN friend_requests fr ON (fr.sender_id = u.id OR fr.receiver_id = u.id)
                           WHERE (fr.sender_id = :user_id OR fr.receiver_id = :user_id) 
                           AND fr.status = 'accepted' 
                           AND u.id != :user_id");
    $stmt->bindParam(":user_id", $loggedInUserId, PDO::PARAM_INT);
    $stmt->execute();
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle friend request actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST["action"];
    $receiverId = $user["id"];

    switch ($action) {
        case "sendFriendRequest":
            // Insert friend request (pending)
            $stmt = $pdo->prepare(
                "INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (:sender_id, :receiver_id, 'pending')"
            );
            $stmt->execute([
                ":sender_id" => $loggedInUserId,
                ":receiver_id" => $receiverId,
            ]);
            break;

        case "acceptFriendRequest":
            // Update friend request status to accepted
            $stmt = $pdo->prepare(
                "UPDATE friend_requests SET status = 'accepted' WHERE sender_id = :sender_id AND receiver_id = :receiver_id"
            );
            $stmt->execute([
                ":sender_id" => $user["id"],
                ":receiver_id" => $loggedInUserId,
            ]);
            break;

        case "declineFriendRequest":
            // Delete friend request
            $stmt = $pdo->prepare(
                "DELETE FROM friend_requests WHERE sender_id = :sender_id AND receiver_id = :receiver_id"
            );
            $stmt->execute([
                ":sender_id" => $user["id"],
                ":receiver_id" => $loggedInUserId,
            ]);
            break;

        case "removeFriend":
            // Remove friend (delete the record from the friend_requests table if they are friends)
            $stmt = $pdo->prepare(
                "DELETE FROM friend_requests WHERE (sender_id = :sender_id AND receiver_id = :receiver_id) OR (sender_id = :receiver_id AND receiver_id = :sender_id)"
            );
            $stmt->execute([
                ":sender_id" => $loggedInUserId,
                ":receiver_id" => $user["id"],
            ]);
            break;
    }
    // Refresh the page after the action
    header("Location: profile.php?user=$username");
    exit();
}

// Fetch the color from the database or set a default
$textColor = htmlspecialchars($user["text_color"] ?? "#000000");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["text_color"])) {
        $textColor = htmlspecialchars($_POST["text_color"]);
        $stmt = $pdo->prepare(
            "UPDATE users SET text_color = :text_color WHERE id = :id"
        );
        $stmt->bindParam(":text_color", $textColor);
        $stmt->bindParam(":id", $_SESSION["user_id"], PDO::PARAM_INT);
        $stmt->execute();
    }
}

// Fetch badges for the user
$badgeStmt = $pdo->prepare(
    "SELECT badge_name FROM user_badges WHERE user_id = :user_id"
);
$badgeStmt->bindParam(":user_id", $user["id"], PDO::PARAM_INT);
$badgeStmt->execute();
$userBadges = $badgeStmt->fetchAll(PDO::FETCH_COLUMN);
$userBadges = array_map("trim", $userBadges);

// Check if the user has specific badges
$hasEmojiCrusherBadge = in_array("Emoji Crusher Badge", $userBadges);
$hasUltraEmojiCrusherBadge = in_array("Ultra Emoji Crusher", $userBadges);
$hasTrophyBadge = in_array("Trophy Badge", $userBadges);
$hasCreatorBadge = in_array("Creator", $userBadges);
$hasGamblerBadge = in_array("Gambling Badge", $userBadges);

// Badge icons (add other badge URLs if needed)
$badgeIcons = [
    "Emoji Crusher Badge" => "badges/emoji_crusher.png",
    "Ultra Emoji Crusher" => "badges/emoji_crusher2.png",
    "Trophy" => "badges/trophy_badge.png",
    "Creator" => "badges/creator.png",
    "Gambling Badge" => "badges/gambler_badge.png",
];

function truncateDescriptionSafe($description)
{
    $firstLine = explode("\n", $description)[0];

    // Truncate to 130 characters without cutting words
    if (strlen($firstLine) > 130) {
        $truncated = substr($firstLine, 0, 130);
        $lastSpace = strrpos($truncated, " ");
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
    } else {
        $truncated = $firstLine;
    }

    return $truncated;
}

$shortBio = htmlspecialchars(truncateDescriptionSafe($user["bio"] ?? ""));
?> 

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@<?php echo htmlspecialchars($user["username"]); ?>'s Profile</title> 
    
    <meta property="og:type" content="website">
    <meta property="og:title" content="@<?php echo htmlspecialchars($user["username"]); ?>'s Profile">
    <meta property="og:description" content="<?php echo $shortBio; ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($user["profile_picture"] ?: 'menutesting/images/account.png'); ?>">
    <meta name="theme-color" content="<?php echo $formColor ?>">

    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #282828;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        /* Gradient background for the form */
        .form-container {
            background: linear-gradient(135deg, <?php echo $formColor; ?>, <?php echo $secondaryColor; ?>);
            border-radius: 10px;
            padding: 20px;
            width: 400px;
            max-height: 90vh; /* Allow it to nearly fill the viewport */
            overflow-y: auto; /* Adds scrolling if form overflows */
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            color: #333;
            position: relative;
            border: 3px solid transparent;
            animation: glow 1.5s ease-in-out infinite alternate; 
            padding-top: 40px; /* Space for button */
        }

        @keyframes glow {
            0% {
                border-color: <?php echo $secondaryColor; ?>;
                box-shadow: 0 0 10px <?php echo $secondaryColor; ?>, 0 0 20px <?php echo $secondaryColor; ?>;
            }
            100% {
                border-color: <?php echo $secondaryColor; ?>;
                box-shadow: 0 0 20px <?php echo $secondaryColor; ?>, 0 0 30px <?php echo $secondaryColor; ?>;
            }
        }

        .profile-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            margin-top: 10px;
        }

        .profile-img {
            border-radius: 50%;
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 3px solid <?php echo $secondaryColor; ?>;
        }

        .profile-info h2 {
            margin: 10px 0 0;
            font-size: 24px;
            color: #34495e;
        } 
        
        .bio-text {
            margin-top: 10px;
            font-size: 16px;
            color: #555;
            white-space: pre-line; /* Preserve line breaks */
        }

        /* Left-align badge container and add tooltip styling */
        .badge-container {
            display: flex;
            gap: 8px;
            justify-content: flex-start; /* Align badges to the left */
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 4px 10px grey;
        }

        .badge {
            width: 30px;
            height: 30px;
            background-size: cover;
            position: relative;
        }

        .badge-signup {
            background-image: url('badges/sign-up-badge.png');
        }

        .badge-og {
            background-image: url('badges/og-badge.png');
        } 
        
                .badge-bug_hunter {
            background-image: url('badges/bug_hunter.png');
        }
        
            .badge-creator_badge { 
            background-image: url('badges/emoji_creator.png');
            } 
        
                .badge-steamhappy_badge { 
            background-image: url('badges/steamhappy.png');
            } 
        
            .badge-gambler_badge { 
            background-image: url('badges/gambler_badge.png');
        
    } 

        /* Tooltip styling */
        .badge:hover::after {
            content: attr(data-tooltip); /* Display text stored in data-tooltip attribute */
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        form {
            margin-top: 20px;
        }

        input[type="color"],
        input[type="file"],
        button {
            background-color: <?php echo $secondaryColor; ?>;
            border: none;
            padding: 10px;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        h1 {
            font-size: 28px;
            color: #f39c12;
        } 
        
        .button-container {
            margin-top: 20px; /* Adds space between last form and button */
        }
        

    .social-media-container img {
        width: 30px; /* Removed inline style and moved here */
        height: 30px; /* Removed inline style and moved here */
        border-radius: 50%; /* Makes the icon rounded */
        transition: transform 0.2s ease-in-out; /* Smooth transition for scaling effect */
        cursor: pointer;
        object-fit: cover; /* Ensures images fill the space proportionally */
    }

    /* Social Media Icons */
    .social-media-icons img {
        width: 30px;
        height: 30px;
        transition: transform 0.2s;
        border-radius: 20%;
    }
    
    .social-media-icons img:hover {
        transform: scale(1.1);
    }

    .button-group {
        position: absolute;
        top: -15px;
        right: 10px;
        display: flex;
        gap: 8px; /* Space between buttons */
        z-index: 10; /* Ensures buttons stay on top */
    }

    .friend-button-group {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        gap: 8px; /* Space between buttons */
        z-index: 10; /* Ensures buttons stay on top */
    }

    .friend-request-button {
        background-color: #f39c12;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s;
    }

    .friend-request-button:hover {
        background-color: #e67e22;
    } 

    .notification-bar {
        background-color: #ffcc00;
        color: #333;
        padding: 10px;
        text-align: center;
        font-weight: bold;
        border-bottom: 1px solid #e0e0e0;
        position: fixed;
        top: 0;
        width: 100%;
        z-index: 1000;
    }

    .notification-bar a.view-requests {
        color: #333;
        text-decoration: underline;
        margin-left: 10px;
    }

    /* Hamburger Menu Styles */
    .hamburger-menu {
        position: absolute;
        top: 15px;
        left: 15px;
    }

    .menu-icon {
        display: flex;
        flex-direction: column;
        gap: 4px;
        cursor: pointer;
    }

    .menu-icon span {
        width: 25px;
        height: 3px;
        background-color: white;
        border-radius: 3px;
        transition: background-color 0.3s;
    }

    /* Dropdown Menu Styles */
    .dropdown-menu {
        display: none;
        position: absolute;
        top: 40px;
        left: 0;
        background-color: rgba(0, 0, 0, 0.9);
        padding: 10px;
        border-radius: 5px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    }

    .dropdown-menu a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 5px 10px;
        margin: 5px 0;
        border-radius: 5px;
        transition: background-color 0.3s;
    }

    .dropdown-menu a:hover {
        background-color: #f39c12;
    }

    #search {
        position: absolute; 
        top: 10px; 
        right: 10px;
    }

    #searchResults {
        background: #fff; 
        border: 1px solid #ccc; 
        border-radius: 5px; 
        position: absolute; 
        top: 30px; 
        right: 0;
        display: none;
        max-height: 200px; 
        overflow-y: auto; 
        z-index: 1000; 
        width: 100%;
    }

    #searchInput {
        padding: 5px; 
        border-radius: 5px; 
        border: 1px solid #ccc; 
        width: 200px;
    }
</style> 
</head>

<body> 

<div style="">
    <input 
        type="text" 
        id="searchInput" 
        placeholder="Search users..." 
        id="searchInput"
        onkeyup="searchUsers(this.value)"
    >
    <div id="searchResults">
        <!-- Suggestions will appear here -->
    </div>
</div>

<div class="hamburger-menu">
    <div class="menu-icon" onclick="toggleMenu()"> <!-- Hamburger Icon -->
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="dropdown-menu" id="dropdownMenu">
        <a href="https://emojico.net" target="_blank">Home</a>
        <a href="profile.php?user=<?php echo htmlspecialchars($username); ?>">Profile</a>
         <a href="login/account.php">Dashboard</a> 
         <a href="discover.php">Explore</a> 
         <a href="logout.php">Logout</a>
    </div>
</div>

<body style="background-image: url('<?php echo htmlspecialchars($user["background_image"] ?? ''); ?>'); background-size: cover; background-position: center;">
    
    
     <!-- Notification bar for friend requests -->
<?php if ($loggedInUserId && $friendRequestCount > 0): ?>
    <div class="notification-bar">
        You have <?php echo $friendRequestCount; ?> new friend request<?php echo $friendRequestCount > 1 ? 's' : ''; ?>!
        <a href="friend_requests.php" class="view-requests">View</a>
    </div>
<?php endif; ?>



    
            <?php
$usernameColor = htmlspecialchars($user['username_color'] ?? '#000000');
$bioColor = htmlspecialchars($user['bio_color'] ?? '#000000');
?>


  <div class="form-container">
    <!-- Profile Image, Username, Bio, and Badges -->
    <img src="<?php echo htmlspecialchars($user["profile_picture"] ?: 'menutesting/images/account.png'); ?>" 
         alt="Profile Picture" class="profile-img">
   <h2 style="color: <?php echo $textColor; ?>;">@<?php echo htmlspecialchars($user['username']); ?></h2>
    <p style="color: <?php echo $textColor; ?>;"><?php echo htmlspecialchars($user['bio']); ?></p>
    
    
    


    <!-- Badge Container --> 
    
    <?php if ($user['show_badges']): ?>
    <div class="badge-container"> 
        <?php if (!empty($user['badge_signup'])): ?>
            <div class="badge badge-signup" data-tooltip="Sign up badge!"></div>
        <?php endif; ?>
        
        <?php if (!empty($user['badge_og'])): ?>
            <div class="badge badge-og" data-tooltip="OG!"></div>
        <?php endif; ?> 
        
        <?php if ($user['is_dev']): ?>
            <div class="badge badge-dev" style="background-image: url('badges/dev_badge.png');" data-tooltip="Developer!"></div>
        <?php endif; ?> 
        
                 <?php if ($user['bug_hunter']): ?>
            <div class="badge bug-hunter" style="background-image: url('badges/bug_hunter.png');" data-tooltip="Bug Hunter"></div>
        <?php endif; ?> 
        
              <?php if ($hasEmojiCrusherBadge): ?>
            <div class="badge" style="background-image: url('<?php echo $badgeIcons['Emoji Crusher Badge']; ?>');" data-tooltip="Emoji Crusher Badge!"></div>
        <?php endif; ?> 
        
         <?php if ($hasUltraEmojiCrusherBadge): ?>
        <div class="badge" style="background-image: url('<?php echo $badgeIcons['Ultra Emoji Crusher']; ?>');" data-tooltip="Ultra Emoji Crusher Badge!"></div>
    <?php endif; ?> 
    
        <?php if ($hasTrophyBadge): ?>
        <div class="badge" style="background-image: url('<?php echo $badgeIcons['Trophy']; ?>');" data-tooltip="Hold the #1 spot on Emoji Crush"></div>
    <?php endif; ?> 
    

    <?php if ($hasCreatorBadge): ?>
        <div class="badge" style="background-image: url('<?php echo $badgeIcons['Creator']; ?>');" data-tooltip="Create 5+ Custom Emojis!"></div> 
        
    <?php endif; ?> 
    
                     <?php if ($user['creator_badge']): ?>
            <div class="badge creator_badge" style="background-image: url('badges/creator_badge.png');" data-tooltip="Create 5+ Custom Emojis!"></div>
        <?php endif; ?> 

                    <?php if ($hasGamblerBadge): ?>
        <div class="badge" style="background-image: url('<?php echo $badgeIcons['Gambling Badge']; ?>');" data-tooltip="Become a gambling degenerate!"></div>
    <?php endif; ?> 
    
    
                         <?php if ($user['steamhappy']): ?>
            <div class="badge steamhappy_badge" style="background-image: url('badges/steamhappy.png');" data-tooltip="Link your steam account"></div>
        <?php endif; ?> 

    
    
    
    </div>

  <?php else: ?>
        <p></p>
    <?php endif; ?>


    

   <!-- Social Media Icons Display -->
<div class="social-media-icons" style="display: <?php echo (empty($user['x_username']) && empty($user['youtube_username']) && empty($user['instagram_username']) && empty($user['discord_username']))  && empty($user['spotify_username']) && empty($user['lastfm_username']) && empty($user['pinterest_username'])  && empty($user['soundcloud_username']) && empty($user['roblox_username'])  && empty($user['steam_username'] && empty($user['letterbox_username'] && empty($user['cashapp_username']) && empty($user['paypal_username'] && empty($user['wallet_id']&& empty($user['bluesky_username']  && empty($user['twitch_username']))))))? 'none' : 'flex'; ?>; display: flex;
    flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 20px;">
      <?php if (!empty($user['youtube_username'])): ?>
        <a href="https://youtube.com/<?php echo htmlspecialchars($user['youtube_username']); ?>" target="_blank">
            <img src="social/youtube.png" alt="YouTube" style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
        <?php if (!empty($user['instagram_username'])): ?>
        <a href="https://instagram.com/<?php echo htmlspecialchars($user['instagram_username']); ?>" target="_blank">
            <img src="social/instagram.png" alt="Instagram" style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?>
    
            <?php if (!empty($user['twitch_username'])): ?>
        <a href="https://twitch.tv/<?php echo htmlspecialchars($user['twitch_username']); ?>" target="_blank">
            <img src="social/twitch.png" alt="YouTube" style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?>
  
  
    <?php if (!empty($user['x_username'])): ?>
        <a href="https://x.com/<?php echo htmlspecialchars($user['x_username']); ?>" target="_blank">
            <img src="social/x.png" alt="X" style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
        <?php if (!empty($user['bluesky_username'])): ?>
        <a href="https://bsky.app/profile/<?php echo htmlspecialchars($user['bluesky_username']); ?>" target="_blank">
            <img src="social/bluesky.png" alt="X" style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
    
        <?php if (!empty($user['discord_username'])): ?>
        <a href="https://discord.gg/<?php echo htmlspecialchars($user['discord_username']); ?>" target="_blank">
            <img src="social/discord.png" alt="Discord" style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
        <?php if (!empty($user['spotify_username'])): ?>
        <a href="https://open.spotify.com/user/<?php echo htmlspecialchars($user['spotify_username']); ?>" target="_blank">
            <img src="social/spotify.png" alt="Spotify" style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
        <?php if (!empty($user['lastfm_username'])): ?>
        <a href="https://lastfm.com/<?php echo htmlspecialchars($user['pinterest_username']); ?>" target="_blank">
            <img src="social/lastfm.jpg" alt="lastfm"  style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?>
    
        <?php if (!empty($user['pinterest_username'])): ?>
        <a href="https://pinterest.com/<?php echo htmlspecialchars($user['pinterest_username']); ?>" target="_blank">
            <img src="social/pinterest.png" alt="Pinterest"  style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
          <?php if (!empty($user['soundcloud_username'])): ?>
        <a href="https://pinterest.com/<?php echo htmlspecialchars($user['soundcloud_username']); ?>" target="_blank">
            <img src="social/soundcloud.png" alt="soundcloud"  style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?>  
    
              <?php if (!empty($user['roblox_username'])): ?>
        <a href="https://roblox.com/users/<?php echo htmlspecialchars($user['roblox_username']); ?>/profile" target="_blank">
            <img src="social/roblox.png" alt="roblox"  style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
             <?php if (!empty($user['steam_username'])): ?>
        <a href="https://steamcommunity.com/id/<?php echo htmlspecialchars($user['steam_username']); ?>" target="_blank">
            <img src="social/steam.png" alt="steam"  style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?>  
    
                 <?php if (!empty($user['letterbox_username'])): ?>
        <a href="https://letterbox.com/<?php echo htmlspecialchars($user['letterbox_username']); ?>" target="_blank">
            <img src="social/letterboxd.png" alt="letterbox"  style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
                     <?php if (!empty($user['cashapp_username'])): ?>
        <a href="https://cash.app/$<?php echo htmlspecialchars($user['letterbox_username']); ?>" target="_blank">
            <img src="social/cashapp.png" alt="cashapp"  style="width: 30px; height: 30px; border-radius: 20%;">
        </a>
    <?php endif; ?> 
    
                
    
<?php if (!empty($user['wallet_id'])): ?>
    <a href="data:text/plain,<?php echo htmlspecialchars($user['wallet_id']); ?>" 
       onclick="copyToClipboard('<?php echo htmlspecialchars($user['wallet_id']); ?>'); return false;" 
       title="Copy Wallet ID: <?php echo htmlspecialchars($user['wallet_id']); ?>"
       style="text-decoration: none;">
        <div class="wallet-container">
            <img src="social/crypto.png" alt="btc" style="width: 30px; height: 30px; border-radius: 20%;">
        </div>
    </a>
<?php endif; ?>

</div>

    <div class="friend-button-group">
    <!-- Friend Request or Friends List Button -->
    <?php if ($isOwnProfile): ?>
        <!-- Show "Friends List" button if viewing own profile -->
        <button class="friend-request-button" onclick="window.location.href='friends_list.php'">Friends List</button>
    <?php else: ?>
        <?php
            // Determine friend request status
            $stmt = $pdo->prepare("SELECT status, sender_id, receiver_id FROM friend_requests WHERE 
    (sender_id = :sender_id AND receiver_id = :receiver_id) 
    OR (sender_id = :receiver_id AND receiver_id = :sender_id)");
$stmt->execute([':sender_id' => $loggedInUserId, ':receiver_id' => $user['id']]);
$friendRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        </div>

        <div class="button-group">
<?php if ($loggedInUserId): ?>
    <!-- Friend request logic -->
    <form method="POST" action="profile.php?user=<?php echo $username; ?>">
        <?php if (!$friendRequest): ?> 
            <button type="submit" name="action" value="sendFriendRequest" class="friend-request-button">Add Friend</button>
        <?php elseif ($friendRequest['status'] === 'pending'): ?>
            <?php if ($friendRequest['sender_id'] == $loggedInUserId): ?>
                <button type="submit" name="action" value="declineFriendRequest" class="friend-request-button">Cancel Request</button>
            <?php elseif ($friendRequest['receiver_id'] == $loggedInUserId): ?>
                <button type="submit" name="action" value="acceptFriendRequest" class="friend-request-button">Accept Request</button>
                <button type="submit" name="action" value="declineFriendRequest" class="friend-request-button">Decline Request</button>
            <?php endif; ?>
        <?php elseif ($friendRequest['status'] === 'accepted'): ?>
            <button type="submit" name="action" value="removeFriend" class="friend-request-button">Remove Friend</button>
        <?php endif; ?>
    </form>
<?php endif; ?>



    <?php endif; ?>

</div>
</body>
<script>
function searchUsers(query) {
    const searchResults = document.getElementById('searchResults');
    if (query.length < 1) {
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
        return;
    }

    fetch(`search.php?query=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            searchResults.style.display = data.length > 0 ? 'block' : 'none';
            searchResults.innerHTML = data.map(user => `
                <div style="padding: 10px; cursor: pointer;" onclick="goToProfile('${user.username}')">
                    <img src="${user.profile_picture || 'menutesting/images/account.png'}" alt="Profile Picture" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                    ${user.username}
                </div>
            `).join('');
        });
}

function goToProfile(username) {
    window.location.href = `profile.php?user=${username}`;
}
    
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

    // Function to copy the wallet address to clipboard
    function copyToClipboard(walletId) {
        // Create a temporary text input element
        var tempInput = document.createElement("input");
        tempInput.value = walletId;
        document.body.appendChild(tempInput);

        // Select and copy the text
        tempInput.select();
        document.execCommand("copy");

        // Remove the temporary input element
        document.body.removeChild(tempInput);

        // Optional: Alert user that the address is copied
        alert("Bitcoin wallet address copied to clipboard!");
    }
</script>

</html>
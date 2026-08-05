<header>

    <div class="top-bar">

        <a href="index.php" class="logo">

            <i class="fas fa-utensils"></i>

            <h1>Humsafar</h1>

        </a>

        <div class="search-bar">

            <input type="text"
                placeholder="Search for restaurants or food...">

            <i class="fas fa-search"></i>

        </div>

        <div class="user-actions">

            <?php if($isLoggedIn){ ?>

                <a href="cart.php">

                    <i class="fas fa-shopping-cart"></i>

                    Cart

                </a>

                <span>

                    👋 <?= htmlspecialchars($userName) ?>

                </span>

                <a href="logout.php">

                    Logout

                </a>

            <?php } else { ?>

                <a href="login.php">

                    Sign In

                </a>

                <a href="register.php">

                    Sign Up

                </a>

            <?php } ?>

        </div>

    </div>

</header>
<?php include 'load_restaurants.php'; ?>

<h2 class="section-title">Popular Restaurants</h2>

<div class="restaurants">

<?php while($restaurant = $result->fetch_assoc()) { ?>

<div class="restaurant-card">

    <img src="assets/images/restaurants/<?php echo $restaurant['image']; ?>"
     style="width:250px;height:180px;border:2px solid red;"
     alt="">

    <div class="restaurant-info">

        <h3><?php echo htmlspecialchars($restaurant['name']); ?></h3>

        <p><?php echo htmlspecialchars($restaurant['description']); ?></p>

        <div class="restaurant-meta">

            ⭐ <?php echo $restaurant['rating']; ?>

            |

            🕒 <?php echo htmlspecialchars($restaurant['delivery_time']); ?>

            |

            Rs. <?php echo $restaurant['delivery_fee']; ?>

        </div>

    </div>

</div>

<?php } ?>

</div>
<?php include 'load_categories.php'; ?>

<h2 class="section-title">Browse by Category</h2>

<div class="categories">

<?php while($category = $result->fetch_assoc()) { ?>

    <div class="category-card">
        <img src="assets/images/categories/<?php echo $category['image']; ?>"
     alt="<?php echo htmlspecialchars($category['name']); ?>"
     style="width:120px;height:120px;border:2px solid red;">

        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
    </div>

<?php } ?>

</div>
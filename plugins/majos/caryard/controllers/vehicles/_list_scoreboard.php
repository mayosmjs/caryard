<?php
    $total = \Majos\Caryard\Models\Vehicle::count();
    $active = \Majos\Caryard\Models\Vehicle::where('is_active', 1)->count();
    $inactive = \Majos\Caryard\Models\Vehicle::where('is_active', 0)->count();
?>

<div class="scoreboard-item control-chart" data-control="chart-pie">
    <ul>
        <li data-color="#95b753">Active <span><?= $active ?></span></li>
        <li data-color="#e6673e">Inactive <span><?= $inactive ?></span></li>
        <li data-color="#34495e">Total <span><?= $total ?></span></li>
    </ul>
</div>


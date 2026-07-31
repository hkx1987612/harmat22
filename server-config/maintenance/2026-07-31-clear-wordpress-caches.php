<?php

if (function_exists('wp_cache_clear_cache')) {
    wp_cache_clear_cache();
    fwrite(STDOUT, "WP_SUPER_CACHE=CLEARED\n");
} else {
    fwrite(STDOUT, "WP_SUPER_CACHE=UNAVAILABLE\n");
}

if (class_exists('autoptimizeCache')) {
    autoptimizeCache::clearall();
    fwrite(STDOUT, "AUTOPTIMIZE=CLEARED\n");
} else {
    fwrite(STDOUT, "AUTOPTIMIZE=UNAVAILABLE\n");
}

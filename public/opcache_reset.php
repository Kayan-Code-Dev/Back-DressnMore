<?php
if (function_exists("opcache_reset")) {
    opcache_reset();
    echo "OPcache reset via web";
} else {
    echo "OPcache not available";
}
unlink(__FILE__);

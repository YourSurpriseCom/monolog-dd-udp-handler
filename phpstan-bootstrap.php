<?php

namespace DDTrace;

//Stubs don't work for creating functions
if (!function_exists("DDTrace\current_context")) {
    function current_context() {
        //do nothing
    }
}

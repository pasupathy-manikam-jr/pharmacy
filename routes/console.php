<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('pharmacy:stock-digest')->dailyAt('07:47');

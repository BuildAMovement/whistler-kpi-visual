<?php
include_once '../application/init.php';

header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');

Application::getInstance()->run();

<?php

namespace itemsviewer;

class core_transactionfunctions
{

    function generate_transactioncode()
    {
        $code = ""; //variable for the transaction code
        $code += time(); //add the current timestamp
        $code += rand(1, 9); //add 5 random numbers
        $code += rand(1, 9);
        $code += rand(1, 9);
        $code += rand(1, 9);
        $code += rand(1, 9);
    }

}

?>
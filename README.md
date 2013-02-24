Evaluator
=========

Safe and simple arithmetic expressions evaluator for PHP

It is often needed, in a computer program, to allow the user to input some logic (expressions to evaluate, simple tests...).
PHP does have an 'eval' function but it is not safe : the user can do anything he wants and validating code passed to eval is hard.

This simple class provides an arithmetic evaluator for PHP.

Just include the "Evaluator.php" file and then use it as follows:

> <?php  
> include_once 'Evaluator.php';  
> 
> $ev = new Evaluator('(2*2+3)/7 + 8 == $var');  
> $result = $ev->evaluate(array('$var' => 9));  
> echo $result;    

This will display 1! Because 9 == 9 and yes, you can use variables and set them at evaluation time!
As illustrated by this example, booleans are converted to 0 or 1.

Variables are expected to be numbers and no check is performed. The Expression string is not validated (yet), so if there is a parse error you will only notice it at evaluation time, sorry!

<?php

class Evaluator
{

	//operators by order of precedence and with their arity
	private $operators = array( '!'  => 1,
								'/'  => 2,
								'*'  => 2,
								'-'  => 2,
								'+'  => 2,
								'<'  => 2,
								'>'  => 2,
								'<=' => 2,
								'>=' => 2,
								'&&' => 2, 
								'||' => 2,
								'!=' => 2,
								'==' => 2);

	public function __construct($expression)
	{
		$number   = '/\b\d+(?:\.\d+)?\b/';
		$variable = '/\$\w+/';
		$operator = '/[\!&\|+\-<>=\\/\*]+/';

		$numbers = array();
		preg_match_all($number, $expression, $numbers);
		$numbers = $numbers[0];

		$variables = array();
		preg_match_all($variable, $expression, $variables);
		$variables = $variables[0];

		$operators = array();
		preg_match_all($operator, $expression, $operators);
		$operators = $operators[0];

		$expression = preg_replace($variable  , "v", $expression);
		$expression = preg_replace($number    , "n", $expression);
		$expression = preg_replace($operator  , "o", $expression);
		
		$nodes = array();
		$group = &$nodes;
		$stack = array();
		for($i = 0; $i < strlen($expression); $i+=1)
		{			
			if($expression[$i] == 'v')
			{
				$group[] = array('type' => 'variable', 'value' => array_shift($variables));
			}
			else if($expression[$i] == 'n')
			{
				$group[] = array('type' => 'number', 'value' => (float)array_shift($numbers));
			}
			else if($expression[$i] == 'o')
			{
				$group[] = array('type' => 'operator', 'value' => array_shift($operators));
			}
			else if($expression[$i] == '(')
			{
				if(isset($elements))unset($elements);
				$elements = array();
				$subgroup = array('type' => 'group', 'nodes' => &$elements);
				$group[]  = $subgroup;
				$stack[]  = &$group;
				unset($group);
				$group    = &$elements;
			}
			else if($expression[$i] == ')')
			{
				$top = &$stack[count($stack) - 1];
				array_pop($stack);
				$group = &$top;
			}
		}

		$nodes = array('type' => 'group', 'nodes' => $nodes);

		$this->canonicalize($nodes);
		$this->apply_precedence($nodes);
		$this->canonicalize($nodes);

		$this->ast = $nodes;
	}

	public function getParsedExpression()
	{
		return $this->toString($this->ast);
	}

	public function evaluate($arguments = array())
	{
		return $this->reduce($this->ast, $arguments);
	}

	private function compute($operator, $arguments)
	{
		if($operator == '!')return (int)(!$arguments[0]);
		else if($operator == '/')return $arguments[0] / $arguments[1];
		else if($operator == '*')return $arguments[0] * $arguments[1];
		else if($operator == '-')return $arguments[0] - $arguments[1];
		else if($operator == '+')return $arguments[0] + $arguments[1];
		else if($operator == '&&')return (int)($arguments[0] && $arguments[1]);
		else if($operator == '||')return (int)($arguments[0] || $arguments[1]);
		else if($operator == '<')return (int)($arguments[0] < $arguments[1]);
		else if($operator == '>')return (int)($arguments[0] > $arguments[1]);
		else if($operator == '<=')return (int)($arguments[0] <= $arguments[1]);
		else if($operator == '>=')return (int)($arguments[0] >= $arguments[1]);
		else if($operator == '!=')return (int)($arguments[0] != $arguments[1]);
		else if($operator == '==')return (int)($arguments[0] == $arguments[1]);
		else throw new Exception("Unknown operator $operator!");
	}

	private function reduce($node, $arguments)
	{
		if($node['type'] == 'application')
		{
			$ops = array();
			foreach($node['operands'] as $operand)
			{
				$ops[] = $this->reduce($operand, $arguments);
			}
			return $this->compute($node['operator'], $ops);
		}
		else if($node['type'] == 'number')return $node['value'];
		else if($node['type'] == 'variable')
		{
			if(isset($arguments[$node['value']]))
			{
				return $arguments[$node['value']];
			}
			else throw new Exception("Variable " . $node['value'] . " was not assigned!");
		}
		else throw new Exception("Don't know how to reduce node with type " . $node['type']);
	}

	private function toString($node)
	{
		if($node['type'] == 'group')
		{
			return '[ ' . implode(' ', array_map(array($this,'toString'), $node['nodes'])) . ' ]';
		}
		else if($node['type'] == 'application')
		{
			if($this->operators[$node['operator']] == 1)
			{
				return '( ' . $node['operator'] . $this->toString($node['operands'][0]) . ' )';
			}
			else
			{
				return '( ' . $this->toString($node['operands'][0]) . ' ' . $node['operator'] . ' ' . $this->toString($node['operands'][1]) . ' )';
			}
		}
		else
		{
			return $node['value'];
		}
	}

	//remove superfluous parentheses
	private function canonicalize(&$node)
	{
		if($node['type'] == 'group')
		{
			foreach($node['nodes'] as &$child)
			{
				$this->canonicalize($child);
			}
			if(count($node['nodes']) == 1)
			{
				$node = $node['nodes'][0];
			}
		}
		else if($node['type'] == 'application')
		{
			foreach($node['operands'] as &$child)
			{
				$this->canonicalize($child);
			}
		}
	}

	private function apply_precedence(&$node)
	{
		if($node['type'] == 'group')
		{
			foreach($node['nodes'] as &$child)
			{
				$this->apply_precedence($child);
			}
			foreach($this->operators as $operator => $arity)
			{
				do{
					$index = -1;
					for($i = 0; $i < count($node['nodes']); $i += 1)
					{
						if(($node['nodes'][$i]['type'] == 'operator') and ($node['nodes'][$i]['value'] == $operator))
						{
							$index = $i;
							break;
						}
					}
					if($index >= 0)
					{
						$new_nodes = ($arity == 1)?array_slice($node['nodes'], 0, $index):array_slice($node['nodes'], 0, $index - 1);
						$operands  = ($arity == 1)?array($node['nodes'][$index+1]):array($node['nodes'][$index-1],$node['nodes'][$index+1]);
						$application = array('type' => 'application', 'operator' => $operator, 'operands' => $operands);
						$new_nodes[] = $application;
						$new_nodes = array_merge($new_nodes, array_slice($node['nodes'], $index + 2));
						$node['nodes'] = $new_nodes;
					}
				}while($index >= 0);
			}
		}
	}

}
<?php
/**
 * Created by PhpStorm.
 * User: dx
 * Date: 08.09.17
 * Time: 18:17
 */

namespace CodexSoft\DatabaseFirst\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecuteClosureCommand extends Command
{

    /** @var \Closure */
    private $closure;

    public function __construct(\Closure $closure, string $name = null)
    {
        parent::__construct($name);
        $this->closure = $closure;
        $this->setDescription('Executes closure');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Executing closure script');
        $closure = $this->closure;
        $closure($this, $input, $output);
    }

}

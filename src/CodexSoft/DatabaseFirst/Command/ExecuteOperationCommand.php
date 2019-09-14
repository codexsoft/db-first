<?php
/**
 * Created by PhpStorm.
 * User: dx
 * Date: 08.09.17
 * Time: 18:17
 */

namespace CodexSoft\DatabaseFirst\Command;

use CodexSoft\OperationsSystem\Operation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @deprecated use CodexSoft\OperationsSystem\Command\ExecuteOperationCommand
 */
class ExecuteOperationCommand extends Command
{

    /** @var Operation */
    private $operation;

    /**
     * function(array $options, Operation $operation) { ... }
     * @var \Closure
     */
    private $configCallback;

    public function __construct(Operation $operation, \Closure $configCallback = null, string $name = null)
    {
        parent::__construct($name);
        $this->configCallback = $configCallback;
        $this->operation = $operation;
        $this->setDescription('Executes operation '.\get_class($this->operation));
    }

    /**
     * @param \Closure $c
     *
     * @return static
     */
    public function setConfigureCallback(\Closure $c): self
    {
        $this->configCallback = $c;
        return $this;
    }

    protected function configure()
    {
        parent::configure();
        $this->addOption('arg', 'a', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Optional arguments');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(sprintf('Executing operation "<info>%s</info>"', \get_class($this->operation)));

        $callback = $this->configCallback;
        if ($callback instanceof \Closure) {
            //$callback($input, $this->operation);
            $callback($input->getOption('arg'), $this->operation);
        }
        //die('Arguments: '.var_export($input->getOption('arg'), true));
        $this->operation->execute();
    }

}

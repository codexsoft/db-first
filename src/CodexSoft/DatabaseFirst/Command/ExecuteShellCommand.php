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


/**
 * @deprecated use CodexSoft\Code\Command\ExecuteShellCommand
 */
class ExecuteShellCommand extends Command
{

    /** @var string[] */
    private $cmds;

    public function __construct(array $cmds, string $name = null)
    {
        parent::__construct($name);
        $this->cmds = $cmds;
        $this->setDescription('Executes shell scripts');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->cmds as $cmd) {
            $out = [];
            $output->writeln("Executing script $cmd");
            exec(escapeshellcmd($cmd), $out, $code);
            foreach($out as $line) {
                $output->writeln($line);
            }
        }
    }

}

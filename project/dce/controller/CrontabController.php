<?php
namespace dce\controller;

use dce\event\Daemon;
use dce\project\node\Node;
use dce\service\cron\Crontab;

class CrontabController extends DceController {
    #[Node('crontab', controllerPath: true)]
    public function __init(): void {
        parent::__init();
    }

    #[Node]
    public function start(): void {
        Daemon::tryRunService(Daemon::ServiceCron);
    }

    #[Node(lazyMatch: true)]
    public function run(): void {
        Crontab::inst()->run($this->rawRequest->remainingPaths[0] ?? '', time());
    }

    #[Node]
    public function status(): void {
        $this->print(Crontab::inst()->showLog(true));
    }

    #[Node]
    public function history(): void {
        $this->print(Crontab::inst()->showLog(false));
    }
}
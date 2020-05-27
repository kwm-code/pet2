<?php
declare(strict_types=1);

namespace GeorgRinger\Pet\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ImportCommand extends Command
{
    protected function configure()
    {
        $this->setDescription('Import pets')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to import')
            ->addArgument('pageId', InputArgument::OPTIONAL, 'Page ID where records are saved');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $file = PATH_site . $input->getArgument('file') ?? '';
        $pageId = (int)($input->getArgument('pageId') ?? 0);

        if (file_exists($file)) {
            $io->title('Start import');
            $count = $this->import($file, $pageId);
            $io->success(sprintf('Records imported! %s new, %s updates', $count['insert'], $count['update']));
        } else {
            $io->error(sprintf('File "%s" does not exist', $file));
        }
    }

    protected function import(string $file, int $pageId): array
    {
        $content = json_decode(file_get_contents($file), true);

        $tableName = 'tx_pet_domain_model_pet';
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);

        $count = [
            'update' => 0,
            'insert' => 0,
        ];

        $types = $this->getAllTypes();

        foreach ($content as $item) {
            $record = BackendUtility::getRecord($tableName, $item['id']);
            if ($record) {
                $update = [
                    'pid' => $pageId,
                    'name' => $item['name'],
                    'pet_type' => $types[$item['type']] ?? 0
                ];
                $count['update']++;
                $connection->update($tableName, $update, ['uid' => $item['id']]);
            } else {
                $insert = [
                    'pid' => $pageId,
                    'uid' => $item['id'],
                    'name' => $item['name']
                ];
                $count['insert']++;
                $connection->insert($tableName, $insert);
            }

        }

        return $count;
    }

    protected function getAllTypes(): array
    {
        $tableName = 'tx_pet_domain_model_pettype';
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);

        $rows = $queryBuilder->select('uid', 'name')
            ->from($tableName)
            ->execute()
            ->fetchAll();

        $data = [];
        foreach ($rows as $row) {
            $data[$row['name']] = $row['uid'];
        }
        return $data;
    }

}

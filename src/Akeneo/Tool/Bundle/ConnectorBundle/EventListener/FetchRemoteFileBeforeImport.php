<?php

namespace Akeneo\Tool\Bundle\ConnectorBundle\EventListener;

use Akeneo\Tool\Component\Batch\Event\EventInterface;
use Akeneo\Tool\Component\Batch\Event\JobExecutionEvent;
use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Model\JobInstance;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\FilesystemInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * As the import system only work on local file, we need to fetch the file to import
 * from a Flysystem storage if it's on one of them.
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class FetchRemoteFileBeforeImport implements EventSubscriberInterface
{
    /** @var FilesystemInterface */
    private $filesystem;

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EventInterface::BEFORE_JOB_EXECUTION => 'fetchRemoteFile'
        ];
    }

    /**
     * Fetch a remote file if needed in case of import
     */
    public function fetchRemoteFile(JobExecutionEvent $event): void
    {
        $jobExecution = $event->getJobExecution();
        $jobParameters = $jobExecution->getJobParameters();

        if (null === $jobParameters ||
            !$jobParameters->has('filePath') ||
            JobInstance::TYPE_IMPORT !== $jobExecution->getJobInstance()->getType()) {
            return;
        }

        $filePath = $jobParameters->get('filePath');

        if (!$this->filesystem->getAdapter() instanceof LocalAdapter ||
            !('/' === $this->filesystem->getAdapter()->getPathPrefix())) {
            if (!$this->filesystem->has($filePath)) {
                throw new \InvalidArgumentException(
                    sprintf('Unable to find the file %s on the configured filesystem adapter %s',
                        $filePath,
                        get_class($this->filesystem->getAdapter())
                    )
                );
            }

            $workingDirectory = $jobExecution->getExecutionContext()->get(JobInterface::WORKING_DIRECTORY_PARAMETER);
            $localFilePath = $workingDirectory.DIRECTORY_SEPARATOR.basename($filePath);

            $remoteStream = $this->filesystem->readStream($filePath);

            file_put_contents($localFilePath, $remoteStream);
            fclose($remoteStream);

            $jobParameters->set('filePath', $localFilePath);
        }
    }
}

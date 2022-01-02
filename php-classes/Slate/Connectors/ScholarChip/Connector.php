<?php

namespace Slate\Connectors\ScholarChip;


use Media;
use ZipArchive;
use Slate\People\Student;
use Emergence\Connectors\IJob;

class Connector extends \Emergence\Connectors\AbstractConnector implements \Emergence\Connectors\ISynchronize
{
    // AbstractConnector overrides
    public static $title = 'ScholarChip';
    public static $connectorId = 'scholarchip';


    // workflow implementations
    protected static function _getJobConfig(array $requestData)
    {
        $config = parent::_getJobConfig($requestData);

        $config['studentPhotosZip'] =
            !empty($_FILES['studentPhotos']) && $_FILES['studentPhotos']['error'] === UPLOAD_ERR_OK
                ? $_FILES['studentPhotos']['tmp_name']
                : null;

        return $config;
    }

    public static function synchronize(IJob $Job, $pretend = true)
    {
        if ($Job->Status != 'Pending' && $Job->Status != 'Completed') {
            return static::throwError('Cannot execute job, status is not Pending or Complete');
        }

        // update job status
        $Job->Status = 'Pending';

        if (!$pretend) {
            $Job->save();
        }


        // init results struct
        $results = [];


        // execute tasks based on available spreadsheets
        if (!empty($Job->Config['studentPhotosZip'])) {
            $studentPhotosZip = new ZipArchive();
            $studentPhotosZip->open($Job->Config['studentPhotosZip']);
            $results['pull-student-photos'] = static::pullStudentPhotos($Job, $studentPhotosZip, $pretend);
        }

        // save job results
        $Job->Status = 'Completed';
        $Job->Results = $results;

        if (!$pretend) {
            $Job->save();
        }

        return true;
    }


    // task handlers
    public static function pullStudentPhotos(IJob $Job, ZipArchive $studentPhotosZip, $pretend = true)
    {
        $results = [
            'skipped' => [],
            'saved' => 0
        ];
        $tempName = tempnam(sys_get_temp_dir(), 'student-photo');

        for ($i = 0; $i < $studentPhotosZip->numFiles; $i++ ) {
            $stat = $studentPhotosZip->statIndex($i);

            if (!preg_match('/^(\d+)\.jpg$/', $stat['name'], $filenameMatches)) {
                $Job->notice("Skipping file {$stat['name']}");
                $results['skipped']['filename-pattern']++;
                continue;
            }

            $Job->debug("Processing file {$stat['name']}");
            $studentNumber = $filenameMatches[1];
            $Student = Student::getByField('StudentNumber', $studentNumber);

            if (!$Student) {
                $Job->error("Could not find student for number {$studentNumber}");
                $results['skipped']['student-not-found']++;
                continue;
            }

            $studentPhotoStream = $studentPhotosZip->getStream($stat['name']);
            file_put_contents($tempName, $studentPhotoStream);

            if ($Student->PrimaryPhoto
                && md5_file($Student->PrimaryPhoto->FilesystemPath) == md5_file($tempName)
            ) {
                $Job->info("Photo {$stat['name']} matches existing for student {$Student->Username}");
                $results['skipped']['matches-existing']++;
                continue;
            }

            $Student->PrimaryPhoto = Media::createFromFile(
                $tempName,
                [
                    'Context' => $Student,
                    'Caption' => "ScholarChip: {$Student->FullName}"
                ]
            );

            $Student->save();

            $Job->notice("Saved photo {$stat['name']} to student {$Student->Username}");
            $results['saved']++;
        }

        return $results;
    }
}

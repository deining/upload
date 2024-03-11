<?php
declare(strict_types=1);

namespace Sirius\Upload;

use Psr\Http\Message\UploadedFileInterface;
use Sirius\Upload\Result\ResultInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface UploadHandlerInterface
{

    /**
     * This function will process the files received from $_FILES,
     * validate them and save them into the container.
     *
     * Along with the file saved into the container a .lock file should
     * be added by the container save() method so, in case the form is
     * not validated, the uploaded file will be removed.
     *
     * @param array<string, mixed>|UploadedFileInterface|UploadedFile $files
     *
     * @return Result\Collection|Result\File|ResultInterface
     */
    public function process(mixed $files): mixed;
}

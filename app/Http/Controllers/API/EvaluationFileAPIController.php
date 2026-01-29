<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateEvaluationFileAPIRequest;
use App\Http\Requests\API\UpdateEvaluationFileAPIRequest;
use App\Models\EvaluationFile;
use App\Repositories\EvaluationFileRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;
use App\Http\Resources\EvaluationFileResource;
use App\Models\EvaluationHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class EvaluationFileController
 */

class EvaluationFileAPIController extends AppBaseController
{
    /** @var  EvaluationFileRepository */
    private $evaluationFileRepository;

    public function __construct(EvaluationFileRepository $evaluationFileRepo)
    {
        $this->evaluationFileRepository = $evaluationFileRepo;
    }

    /**
     * @OA\Get(
     *      path="/evaluation-files",
     *      summary="getEvaluationFileList",
     *      tags={"EvaluationFile"},
     *      description="Get all EvaluationFiles",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/EvaluationFile")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $evaluationFiles = $this->evaluationFileRepository->all(
            $request->except(['skip', 'limit', 'search', 'exclude', 'user', 'perPage', 'order', 'orderColumn', 'page', 'with']),
            $request->get('search'),
            $request->get('skip'),
            $request->get('limit'),
            $request->perPage,
            $request->get('with', []),
            $request->get('order', 'desc'),
            $request->get('orderColumn', 'id')
        );

        return $this->sendResponse($evaluationFiles, 'Evaluation Files retrieved successfully');
    }

    /**
     * @OA\Post(
     *      path="/evaluation-files",
     *      summary="createEvaluationFile",
     *      tags={"EvaluationFile"},
     *      description="Create EvaluationFile",
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/EvaluationFile")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/EvaluationFile"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function store(CreateEvaluationFileAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        if(!empty($input['file'])) {
            $base64File = $request->input('file');

            if (preg_match('/^data:([\w\/\-\+]+);base64,/', $base64File, $type)) {
                $fileData = substr($base64File, strpos($base64File, ',') + 1);
                $fileData = base64_decode($fileData);

                if ($fileData === false) {
                    $this->sendError('base64_decode failed');
                }
            } else {
                $this->sendError('did not match data URI with image data');
            }

            $mimeType = $type[1];
            $extension = '';

            $mimeMap = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/gif' => 'gif',
                    'image/bmp' => 'bmp',
                    'image/tiff' => 'tiff',
                    'image/svg+xml' => 'svg',
                    'application/pdf' => 'pdf',
                    'video/mp4' => 'mp4',
                    'video/quicktime' => 'mov',
                    'video/x-m4v' => 'm4v',
                    'video/webm' => 'webm',
                    'video/ogg' => 'ogv',
                    'video/3gpp' => '3gp',
                    'video/3gpp2' => '3g2',
                    'image/webp' => 'webp',
            ];

            if (isset($mimeMap[$mimeType])) {
                $extension = $mimeMap[$mimeType];
            } else {
                throw new \Exception('No valid mime type found for file');
            }

            $fileName = 'files/'.time(). '.' . $extension;
            Storage::disk('public')->put($fileName, $fileData);
            $input['file'] = url(Storage::url($fileName));

        } else {
            return $this->sendError('Evaluation File cannot be created without a file');
        }

        $evaluationFile = $this->evaluationFileRepository->create($input);
        $this->logFileHistory($evaluationFile, $request, 'file_added');

        return $this->sendResponse(new EvaluationFileResource($evaluationFile), 'Evaluation File saved successfully');
    }

    /**
     * @OA\Get(
     *      path="/evaluation-files/{id}",
     *      summary="getEvaluationFileItem",
     *      tags={"EvaluationFile"},
     *      description="Get EvaluationFile",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of EvaluationFile",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/EvaluationFile"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show($id, Request $request): JsonResponse
    {
        /** @var EvaluationFile $evaluationFile */
        $evaluationFile = $this->evaluationFileRepository->find($id, with: $request->get('with', []));

        if (empty($evaluationFile)) {
            return $this->sendError('Evaluation File not found');
        }

        return $this->sendResponse($evaluationFile, 'Evaluation File retrieved successfully');
    }

    /**
     * @OA\Put(
     *      path="/evaluation-files/{id}",
     *      summary="updateEvaluationFile",
     *      tags={"EvaluationFile"},
     *      description="Update EvaluationFile",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of EvaluationFile",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\RequestBody(
     *        required=true,
     *        @OA\JsonContent(ref="#/components/schemas/EvaluationFile")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  ref="#/components/schemas/EvaluationFile"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function update($id, UpdateEvaluationFileAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var EvaluationFile $evaluationFile */
        $evaluationFile = $this->evaluationFileRepository->find($id, with: $request->get('with', []));

        if (empty($evaluationFile)) {
            return $this->sendError('Evaluation File not found');
        }

        if(!empty($input['file'])) {
            $base64File = $request->input('file');

            if (preg_match('/^data:([\w\/\-\+]+);base64,/', $base64File, $type)) {
                $fileData = substr($base64File, strpos($base64File, ',') + 1);
                $fileData = base64_decode($fileData);

                if ($fileData === false) {
                    $this->sendError('base64_decode failed');
                }
            } else {
                $this->sendError('did not match data URI with image data');
            }

            $mimeType = $type[1];
            $extension = '';

            $mimeMap = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/gif' => 'gif',
                    'image/bmp' => 'bmp',
                    'image/tiff' => 'tiff',
                    'image/svg+xml' => 'svg',
                    'application/pdf' => 'pdf',
                    'video/mp4' => 'mp4',
                    'video/quicktime' => 'mov',
                    'video/x-m4v' => 'm4v',
                    'video/webm' => 'webm',
                    'video/ogg' => 'ogv',
                    'video/3gpp' => '3gp',
                    'video/3gpp2' => '3g2',
                    'image/webp' => 'webp',
            ];

            if (isset($mimeMap[$mimeType])) {
                $extension = $mimeMap[$mimeType];
            } else {
                throw new \Exception('No valid mime type found for file');
            }

            $fileName = 'files/'.time(). '.' . $extension;
            Storage::disk('public')->put($fileName, $fileData);
            $input['file'] = url(Storage::url($fileName));

        } else {
            $input = $request->except('file');
        }

        $evaluationFile = $this->evaluationFileRepository->update($input, $id);

        return $this->sendResponse(new EvaluationFileResource($evaluationFile), 'EvaluationFile updated successfully');
    }

    /**
     * @OA\Delete(
     *      path="/evaluation-files/{id}",
     *      summary="deleteEvaluationFile",
     *      tags={"EvaluationFile"},
     *      description="Delete EvaluationFile",
     *      @OA\Parameter(
     *          name="id",
     *          description="id of EvaluationFile",
     *           @OA\Schema(
     *             type="integer"
     *          ),
     *          required=true,
     *          in="path"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function destroy($id): JsonResponse
    {
        /** @var EvaluationFile $evaluationFile */
        $evaluationFile = $this->evaluationFileRepository->find($id);

        if (empty($evaluationFile)) {
            return $this->sendError('Evaluation File not found');
        }

        $this->logFileHistory($evaluationFile, request(), 'file_deleted');
        $evaluationFile->delete();

        return $this->sendSuccess('Evaluation File deleted successfully');
    }

    private function logFileHistory(EvaluationFile $file, Request $request, string $type): void
    {
        $user = $request->user() ?? auth('sanctum')->user();

        EvaluationHistory::create([
            'evaluation_id' => $file->evaluation_id,
            'user_id' => $user?->id,
            'monitor_id' => $this->resolveMonitorId($user),
            'type' => $type,
            'payload' => array_merge([
                'file_id' => $file->id,
                'file_type' => $file->type,
                'file' => $file->file,
            ], $this->getCourseContext($request)),
        ]);
    }

    private function resolveMonitorId(?\App\Models\User $user): ?int
    {
        if (!$user) {
            return null;
        }

        $type = $user->type;
        if ($type !== 3 && $type !== 'monitor') {
            return null;
        }

        return $user->monitors()
            ->orderByDesc('active_school')
            ->orderByDesc('id')
            ->value('id');
    }

    private function getCourseContext(Request $request): array
    {
        $payload = [];
        $courseId = $request->input('course_id');
        $courseName = $request->input('course_name');

        if ($courseId) {
            $payload['course_id'] = $courseId;
        }

        if ($courseName) {
            $payload['course_name'] = $courseName;
        }

        return $payload;
    }
}

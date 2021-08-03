<?php
declare(strict_types=1);

namespace admin\modules\CompanyBranch\actions;

use admin\modules\Auth\requests\AdminPermissionRequest;
use admin\modules\CompanyBranch\requests\CompanyBranchCreateRequest;
use admin\modules\CompanyBranch\responses\CompanyBranchViewResponse;
use admin\modules\CompanyBranch\services\CompanyBranchCreateService;
use api\v2\models\Message;
use app\base\HttpStatusCode;
use app\modules\Log\services\LogServiceAwareTrait;
use common\modules\Action\interfaces\ActionRunInterface;
use common\modules\Action\requests\CombineRequest;
use common\modules\Action\responses\BaseResponse;

class CompanyBranchCreateAction implements ActionRunInterface
{
    use LogServiceAwareTrait;

    /**
     * @var CompanyBranchCreateService
     */
    private $companyBranchCreateService;

    public function __construct(
        CompanyBranchCreateService $companyBranchProvider
    ) {
        $this->companyBranchCreateService = $companyBranchProvider;
    }

    public function run($request, $response, array $params = []): bool
    {
        \assert($request instanceof CombineRequest);
        \assert($response instanceof BaseResponse);

        $permissions = $request->subRequest(AdminPermissionRequest::class)->getPermissions();

        /** @var CompanyBranchCreateRequest $companyBranchCreateRequest */
        $companyBranchCreateRequest = $request->subRequest(CompanyBranchCreateRequest::class);

        /** @var CompanyBranchViewResponse $companyResponse */
        $companyResponse = $response->getSubResponse(CompanyBranchViewResponse::class);

        $companyBranchCreateForm = $companyBranchCreateRequest->getCompanyBranchCreateForm();
        if (! $companyBranchCreateForm->isValid()) {
            $response->setStatusCode(HttpStatusCode::BAD_REQUEST);
            $response->addMessageCollection($companyBranchCreateForm);

            return false;
        }

        $companyOrderExtraForm = $companyBranchCreateRequest->getCompanyOrderExtraForm();
        if (! $companyOrderExtraForm->isValid()) {
            $response->setStatusCode(HttpStatusCode::BAD_REQUEST);
            $response->addMessageCollection($companyOrderExtraForm);

            return false;
        }
        $company = $companyBranchCreateRequest->getCompany();
        \assert(null !== $company);

        if (
            null !== $this->companyBranchCreateService->findByClientIdAndTitle(
                $company->clientId,
                $companyBranchCreateForm->getAttribute('title')
            )
        ) {
            $response->setHttpCode(HttpStatusCode::BAD_REQUEST);
            $response->pushMessage(Message::globalError(HttpStatusCode::BAD_REQUEST));

            return false;
        }

        try {
            $companyBranchAggregate = $this->companyBranchCreateService->createCompanyBranch(
                $company,
                $companyBranchCreateForm,
                $companyOrderExtraForm,
                $permissions
            );
        } catch (\Exception $e) {
            $this->getLogService()->logForException($e, __METHOD__);
            $response->setStatusCode(HttpStatusCode::INTERNAL_SERVER_ERROR);

            return false;
        }
        if (null === $companyBranchAggregate) {
            $response->setStatusCode(HttpStatusCode::INTERNAL_SERVER_ERROR);

            return false;
        }

        $companyResponse->addCompanyBranchAggregate($companyBranchAggregate);

        return true;
    }
}

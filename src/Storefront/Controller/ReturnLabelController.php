<?php declare(strict_types=1);

namespace ActiveAntsReturnLabelPlugin\Storefront\Controller;

use ActiveAntsReturnLabelPlugin\Library\MayaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ReturnLabelController extends StorefrontController
{
    /**
     * @var SystemConfigService
     */
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(SystemConfigService $systemConfigService, EntityRepositoryInterface $orderRepository)
    {
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/returnLabel/{externalOrderId}", name="frontend.downloadReturnLabel", methods={"GET"})
     */
    public function showReturnLabel($externalOrderId, SalesChannelContext $salesChannelContext, Context $context): Response
    {
        try {
            $this->checkOrder($externalOrderId, $salesChannelContext, $context);
            $mayaService = new MayaService($this->systemConfigService, $this->container);
            $pdfFile = $mayaService->getReturnLabelPDF($externalOrderId);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), $e->getCode());
        }

        return new Response(base64_decode($pdfFile->data), 200,
            array('Content-Type' => $pdfFile->mimeType)
        );
    }

    /**
     * @param $externalOrderId
     * @param $salesChannelContext
     * @param $context
     * @return Response|void
     */
    private function checkOrder($externalOrderId, $salesChannelContext, $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $externalOrderId));

        $order = $this->orderRepository->search($criteria, $context)->first();

        if (empty($order)) {
            throw new \Exception('', 404);
        }

        if ($salesChannelContext->getCustomer()->id != $order->orderCustomer->customerId) {
            throw new \Exception('', 401);
        }
    }
}

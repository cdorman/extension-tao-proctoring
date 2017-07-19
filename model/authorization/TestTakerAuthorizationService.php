<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2016 (original work) Open Assessment Technologies SA;
 *
 */
namespace oat\taoProctoring\model\authorization;

use oat\oatbox\service\ConfigurableService;
use oat\taoDelivery\model\execution\DeliveryExecution;
use oat\taoProctoring\model\execution\DeliveryExecution as ProctoredDeliveryExecution;
use oat\taoDelivery\model\authorization\UnAuthorizedException;
use oat\oatbox\user\User;
use oat\taoDeliveryRdf\model\guest\GuestTestUser;
use oat\taoProctoring\model\execution\ProctoredDeliveryFactoryEventsService;
use oat\taoProctoring\model\execution\ProctoredDeliveryFactoryService;
use oat\taoProctoring\model\ProctorService;
use oat\generis\model\OntologyAwareTrait;
use oat\taoDeliveryRdf\model\event\DeliveryCreatedEvent;
use oat\taoDeliveryRdf\model\event\DeliveryUpdatedEvent;

/**
 * Manage the Delivery authorization.
 *
 * @author Bertrand Chevrier <bertrand@taotesting.com>
 */
class TestTakerAuthorizationService extends ConfigurableService
{
    use OntologyAwareTrait;

    const SERVICE_ID = 'taoProctoring/TestTakerAuthorization';

    const PROCTORED_BY_DEFAULT = 'proctored_by_default';

    /**
     * (non-PHPdoc)
     * @see \oat\taoDelivery\model\authorization\AuthorizationProvider::verifyStartAuthorization()
     * @param $deliveryId
     * @param User $user
     */
    public function verifyStartAuthorization($deliveryId, User $user)
    {
        // always allow start
    }

    /**
     * (non-PHPdoc)
     * @see \oat\taoDelivery\model\authorization\AuthorizationProvider::verifyResumeAuthorization()
     * @param DeliveryExecution $deliveryExecution
     * @param User $user
     * @throws UnAuthorizedException
     */
    public function verifyResumeAuthorization(DeliveryExecution $deliveryExecution, User $user)
    {
        $state = $deliveryExecution->getState()->getUri();

        if (in_array($state, [
            ProctoredDeliveryExecution::STATE_FINISHED,
            ProctoredDeliveryExecution::STATE_CANCELED,
            ProctoredDeliveryExecution::STATE_TERMINATED])
        ) {
            throw new UnAuthorizedException(
                _url('index', 'DeliveryServer', 'taoProctoring'),
                'Terminated/Finished delivery cannot be resumed'
            );
        }
        if ($this->isProctored($deliveryExecution->getDelivery()->getUri(), $user) && $state !== ProctoredDeliveryExecution::STATE_AUTHORIZED) {
            $this->throwUnAuthorizedException($deliveryExecution);
        }
    }

    /**
     * Check if delivery id proctored
     *
     * @param string $deliveryId
     * @param User $user
     * @return bool
     * @internal param core_kernel_classes_Resource $delivery
     */
    public function isProctored($deliveryId, User $user)
    {
        $propertyUri = null;
        $proctoredByDefault = $this->hasOption(self::PROCTORED_BY_DEFAULT)
            ? $this->getOption(self::PROCTORED_BY_DEFAULT)
            : true;

        if ($deliveryId) {
            $delivery = $this->getResource($deliveryId);
            $accessibleProperty = $this->getProperty(ProctorService::ACCESSIBLE_PROCTOR);
            $accessiblePropertyValue = $delivery->getOnePropertyValue($accessibleProperty);
            $propertyUri = $accessiblePropertyValue ? $accessiblePropertyValue->getUri() : null;
        }

        if (
            ($proctoredByDefault && !$propertyUri)
            || ($propertyUri == ProctorService::ACCESSIBLE_PROCTOR_ENABLED && !($user instanceof GuestTestUser))
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Throw the appropriate Exception
     *
     * @param DeliveryExecution $deliveryExecution
     * @throws UnAuthorizedException
     */
    protected function throwUnAuthorizedException(DeliveryExecution $deliveryExecution)
    {
        $errorPage = _url('awaitingAuthorization', 'DeliveryServer', 'taoProctoring', array('deliveryExecution' => $deliveryExecution->getIdentifier()));
        throw new UnAuthorizedException($errorPage, 'Proctor authorization missing');
    }

    /**
     * Whenever or not new deliveries should be proctored by default
     *
     * @param boolean $proctored
     * @return \oat\taoProctoring\model\authorization\TestTakerAuthorizationService
     */
    public function setProctoredByDefault($proctored)
    {
        $this->setOption(self::PROCTORED_BY_DEFAULT, $proctored);
        return $this;
    }

    /**
     * Listen create event for delivery
     * @param DeliveryCreatedEvent $event
     */
    public function onDeliveryCreated(DeliveryCreatedEvent $event)
    {
        $delivery = $this->getResource($event->getDeliveryUri());
        $proctored = $this->getOption(self::PROCTORED_BY_DEFAULT);
        $delivery->editPropertyValues(new \core_kernel_classes_Property(ProctorService::ACCESSIBLE_PROCTOR), (
            $proctored ? ProctorService::ACCESSIBLE_PROCTOR_ENABLED : ProctorService::ACCESSIBLE_PROCTOR_DISABLED
        ));
    }

    /**
     * Listen update event for delivery
     * @param DeliveryUpdatedEvent $event
     */
    public function onDeliveryUpdated(DeliveryUpdatedEvent $event)
    {
        $data = $event->jsonSerialize();
        $deliveryData = !empty($data['data']) ? $data['data'] : [];
        $delivery = $this->getResource($event->getDeliveryUri());
        if (isset($deliveryData[ProctorService::ACCESSIBLE_PROCTOR]) && !$deliveryData[ProctorService::ACCESSIBLE_PROCTOR]) {
            $delivery->editPropertyValues(new \core_kernel_classes_Property(ProctorService::ACCESSIBLE_PROCTOR), ProctorService::ACCESSIBLE_PROCTOR_DISABLED);
        }
    }
}

<?php
namespace App\Controller;


use FacebookAds\Api;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\Fields\AdAccountFields;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 *
 * @Route("ad_account")
 *
 */
class AdAccountController extends AbstractController
{
    private $adAccount;

    /**
     * AdAccountController constructor.
     *
     * @param string $appId
     * @param string $appSecret
     * @param string $accessToken
     * @param string $adAccountId
     */
    public function __construct(
        string $appId,
        string $appSecret,
        string $adAccountId,
        string $accessToken
    )
    {
        Api::init(
            $appId, // App ID
            $appSecret,
            $accessToken // Your user access token
        );

        $this->adAccount = new AdAccount($adAccountId);
    }

    /**
     * @Route("", methods={"GET"})
     *
     * @return Response
     */
    public function getSpendLimit(): Response
    {
        try {
        $this->adAccount->read([AdAccountFields::AMOUNT_SPENT,AdAccountFields::SPEND_CAP]);
        // даныне приходят как numeric string, в центах
        } catch (\Throwable $throwable) {
            return new JsonResponse(
                [
                    'error'  => 'FB error',
                    'message' => $throwable->getMessage(),
                    'trace' => json_encode($throwable->getTrace(), JSON_PRETTY_PRINT)
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $cap = (int)$this->adAccount->{AdAccountFields::SPEND_CAP};
        $spent = (int)$this->adAccount->{AdAccountFields::AMOUNT_SPENT};

        // вернет данные в долларах
        return new JsonResponse(['limit' => ($cap - $spent) / 100]);
    }

    /**
     * @Route("", methods={"POST"})
     *
     * @param Request            $request
     * @param ValidatorInterface $validator
     *
     * @return Response
     */
    public function setSpendLimit(Request $request, ValidatorInterface $validator): Response
    {
        $data = json_decode(
            $request->getContent(),
            true
        );

        $violations = $validator->validate(
            array_merge(
                array_merge(
                    array_fill_keys(['limit'], null),
                    $data
                ),
                $data
            ),
            new Assert\Collection([
            'allowMissingFields' => false,
            'allowExtraFields'   => false,
            'fields'             => [
              'limit'       => [
                  new Assert\NotBlank(),
                  new Assert\Type('float'), // это значение передается как float, в долларах
              ],
            ]
        ]));
        if ($violations->count()) {
            $fields = [];
            foreach ($violations as $violation) {
                /* @var $violation ConstraintViolation */
                $fields[] = $violation->getPropertyPath();
            }

            return new JsonResponse(
                [
                   'error'  => 'invalid argument',
                   'fields' => $fields,
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $this->adAccount->update([AdAccountFields::SPEND_CAP => $request->get('limit')]);
        } catch (\Throwable $throwable) {
            return new JsonResponse(
                [
                    'error'  => 'FB error',
                    'message' => $throwable->getMessage(),
                    'trace' => json_encode($throwable->getTrace(), JSON_PRETTY_PRINT)
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
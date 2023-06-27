<?php

namespace LimeSurvey\Models\Services\SurveyUpdater;

use Survey;
use Permission;
use LSYii_Application;
use PluginEvent;
use Date_Time_Converter;
use LimeSurvey\SessionData;
use LimeSurvey\PluginManager\PluginManager;
use LimeSurvey\Models\Services\Exception\{
    ExceptionPersistError,
    ExceptionNotFound,
    ExceptionPermissionDenied
};

/**
 * Service GeneralSettings
 *
 * Service class for survey language setting updating.
 *
 * Dependencies are injected to enable mocking.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class GeneralSettings
{
    private Permission $modelPermission;
    private Survey $modelSurvey;
    private LSYii_Application $yiiApp;
    private SessionData $sessionData;
    private PluginManager $pluginManager;
    private LanguageConsistency $languageConsistency;

    const FIELD_TYPE_YN = 'yersno';
    const FIELD_TYPE_DATETIME = 'dateime';
    const FIELD_TYPE_GAKEY = 'gakey';
    const FIELD_TYPE_USE_CAPTCHA = 'use_captcha';

    const GA_GLOBAL_KEY = '9999useGlobal9999';

    public function __construct(
        Permission $modelPermission,
        Survey $modelSurvey,
        LSYii_Application $yiiApp,
        SessionData $sessionData,
        PluginManager $pluginManager,
        LanguageConsistency $languageConsistency
    ) {
        $this->modelPermission = $modelPermission;
        $this->modelSurvey = $modelSurvey;
        $this->yiiApp = $yiiApp;
        $this->sessionData = $sessionData;
        $this->pluginManager = $pluginManager;
        $this->languageConsistency = $languageConsistency;
    }

    /**
     * Update
     *
     * @param int $surveyId
     * @param array $input
     * @throws ExceptionPersistError
     * @throws ExceptionNotFound
     * @throws ExceptionPermissionDenied
     * @return array<array-key, mixed>
     */
    public function update($surveyId, $input)
    {
        $input = is_array($input) && !empty($input)
            ? $input
            : [];

        $hasPermission = $this->modelPermission
            ->hasSurveyPermission(
                $surveyId,
                'surveysettings',
                'update'
            );
        if (!$hasPermission) {
            throw new ExceptionPermissionDenied(
                'Permission denied'
            );
        }

        $survey = $this->modelSurvey->findByPk(
            $surveyId
        );
        if (!$survey) {
            throw new ExceptionNotFound();
        }

        return $this->updateGeneralSettings(
            $survey,
            $input
        );
    }

    /**
     * Update General Settings
     *
     * @param Survey $survey
     * @param array $input
     * @throws ExceptionPersistError
     * @throws ExceptionNotFound
     * @throws ExceptionPermissionDenied
     * @return array<array-key, mixed>
     */
    private function updateGeneralSettings(Survey $survey, array $input)
    {
        $initAttributes = $survey->getAttributes();

        $input = is_array($input) && !empty($input)
            ? $input
            : [];

        $this->dispatchPluginEventNewSurveySettings(
            $survey->sid,
            isset($input['plugin']) ? $input['plugin'] : []
        );

        $input = $this->filterInput($input);

        $meta = ['updateFields' => []];

        $fields = $this->getFields($survey);
        foreach ($fields as $field => $fieldOpts) {
            $meta = $this->setField(
                $field,
                $input,
                $survey,
                $meta,
                $fieldOpts
            );
        }

        if (!empty($meta['updateFields'])) {
            $this->languageConsistency->update(
                $survey,
                $initAttributes['language']
            );

            $this->dispatchPluginEventBeforeSurveySettingsSave(
                $survey
            );

            if (!$survey->save()) {
                throw new ExceptionPersistError(
                    sprintf(
                        'Failed saving general settings for survey #%s',
                        $survey->sid
                    )
                );
            }
        }

        return $meta;
    }

    /**
     * Get Database Fields
     *
     * @param Survey $survey
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function getFields(Survey $survey)
    {
        $surveyNotActive = $survey->active != 'Y';

        return [
            'owner_id' => [
                'canUpdate' => (
                    $survey->owner_id == $this->sessionData['loginID']
                    || $this->modelPermission->hasGlobalPermission(
                        'superadmin',
                        'read'
                    )
                )
            ],
            'expires' =>  ['type' => static::FIELD_TYPE_DATETIME],
            'startdate' => ['type' => static::FIELD_TYPE_DATETIME],
            'assessments' => ['type' => static::FIELD_TYPE_YN],
            'anonymized' => [
                'type' => static::FIELD_TYPE_YN,
                'canUpdate' => $surveyNotActive
            ],
            'savetimings' => [
                'type' => static::FIELD_TYPE_YN,
                'canUpdate' => $surveyNotActive
            ],
            'datestamp' => [
                'type' => static::FIELD_TYPE_YN,
                'canUpdate' => $surveyNotActive
            ],
            'ipaddr' => [
                'type' => static::FIELD_TYPE_YN,
                'canUpdate' => $surveyNotActive
            ],
            'ipanonymize' => [
                'type' => static::FIELD_TYPE_YN,
                'canUpdate' => $surveyNotActive
            ],
            'refurl' => [
                'type' => static::FIELD_TYPE_YN,
                'canUpdate' => $surveyNotActive
            ],
            'publicgraphs' => ['type' => static::FIELD_TYPE_YN],
            'usecookie' => ['type' => static::FIELD_TYPE_YN],
            'allowregister' => ['type' => static::FIELD_TYPE_YN],
            'allowsave' => ['type' => static::FIELD_TYPE_YN],
            'navigationdelay' => [],
            'printanswers' => ['type' => static::FIELD_TYPE_YN],
            'publicstatistics' => ['type' => static::FIELD_TYPE_YN],
            'autoredirect' => ['type' => static::FIELD_TYPE_YN],
            'showxquestions' => ['type' => static::FIELD_TYPE_YN],
            'showgroupinfo' => [],
            'showqnumcode' => [],
            'shownoanswer' => ['type' => static::FIELD_TYPE_YN],
            'showwelcome' => ['type' => static::FIELD_TYPE_YN],
            'showsurveypolicynotice' => ['default' => 0],
            'allowprev' => ['type' => static::FIELD_TYPE_YN],
            'questionindex' => [],
            'nokeyboard' => ['type' => static::FIELD_TYPE_YN],
            'showprogress' => ['type' => static::FIELD_TYPE_YN],
            'listpublic' => ['type' => static::FIELD_TYPE_YN],
            'htmlemail' => ['type' => static::FIELD_TYPE_YN],
            'sendconfirmation' => ['type' => static::FIELD_TYPE_YN],
            'tokenanswerspersistence' => ['type' => static::FIELD_TYPE_YN],
            'alloweditaftercompletion' => ['type' => static::FIELD_TYPE_YN],
            'usecaptcha' => [
                'type' => static::FIELD_TYPE_USE_CAPTCHA,
                'compositeInputs' => [
                    'usecaptcha_surveyaccess',
                    'usecaptcha_registration',
                    'usecaptcha_saveandload'
                ]
            ],
            'emailresponseto' => [],
            'emailnotificationto' => [],
            'googleanalyticsapikeysetting' => [],
            'googleanalyticsapikey' => ['type' => static::FIELD_TYPE_GAKEY],
            'googleanalyticsstyle' => [],
            'tokenlength' => [],

            // from Database::actionUpdateSurveyLocaleSettingsGeneralSettings()
            'language' => [],
            'additional_languages' => [],
            'admin' => [],
            'adminemail' => [],
            'bounce_email' => [],
            'gsid' => ['default' => 1],
            'format' => [],
            'template' => []
        ];
    }

    /**
     * Set Field
     *
     * @param string $field
     * @param array $input
     * @param Survey $survey
     * @param array $meta
     * @param ?array $fieldOpts
     * @return array<array-key, mixed>
     * @SuppressWarnings("php:S3776") Cognitive Complexity
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function setField($field, $input, Survey $survey, $meta, $fieldOpts = null)
    {
        $meta = is_array($meta) ? $meta : [
            'updateFields' => []
        ];
        $meta['updateFields'] = is_array($meta['updateFields'])
            ? $meta['updateFields']
            : [];

        $fieldOpts = !empty($fieldOpts)
            ? $fieldOpts
            : [];

        // Composite inputs always have to be processed
        // - even if the field itself is not provided
        $isCompositeInput = (
            isset($fieldOpts['compositeInputs'])
        );

        if (
            !isset($input[$field])
            && !$isCompositeInput
        ) {
            return $meta;
        }

        $type = !empty($fieldOpts['type'])
            ? $fieldOpts['type']
            : null;
        $initValue = $survey->{$field} ?? '';
        $default = !empty($fieldOpts['default'])
            ? $fieldOpts['default']
            : $initValue;

        if (
            isset($fieldOpts['canUpdate'])
            && !$fieldOpts['canUpdate']
        ) {
            return $meta;
        }

        $value = $input[$field] ?? null;
        switch ($type) {
            case static::FIELD_TYPE_DATETIME:
                $value = !empty($value)
                    ? $this->formatDateTimeInput($value)
                    : $default;
                break;
            case static::FIELD_TYPE_YN:
                if (!in_array('' . $value, ['Y', 'N', 'I'])) {
                    $value = ((int) $value === 1) ? 'Y' : 'N';
                }
                break;
            case static::FIELD_TYPE_GAKEY:
                if ($value == 'G') {
                    $value  = static::GA_GLOBAL_KEY;
                } elseif ($value == 'N') {
                    $value = '';
                }
                break;
            case static::FIELD_TYPE_USE_CAPTCHA:
                $value = $input['usecaptcha'] ?? $this->calculateUseCaptchaOption(
                    $input['usecaptcha_surveyaccess'] ?? null,
                    $input['usecaptcha_registration'] ?? null,
                    $input['usecaptcha_saveandload'] ?? null
                );
                if (is_null($value)) {
                    $value = $default;
                }
                break;
            case 'int':
                $value = (int) $value;
                break;
            case null:
            default:
                $value = is_null($value)
                    ? $default
                    : $value;
                break;
        }

        if ($field == 'tokenlength') {
            $value = (int) (
                (
                    ($value  < 5 || $value  > 36)
                    && $value != -1
                )
                ? 15
                : $value
            );
        }
        if (
            $field == 'additional_languages'
            && is_array($value)
        ) {
            if (
                (
                    $index = array_search(
                        $survey->language,
                        $value
                    )
                ) !== false
            ) {
                unset($value[$index]);
            }
            $value = implode(
                ' ',
                $value
            );
        }

        $survey->{$field} = $value;

        if (
            !in_array(
                $field,
                $meta['updateFields']
            )
        ) {
            $meta['updateFields'][] = $field;
        }

        return $meta;
    }

    /**
     * Filter Input
     *
     * @param array $surveyId
     * @return array
     */
    private function filterInput($input)
    {
        return array_map(function ($value) {
            return is_string($value)
                ? trim($value)
                : $value;
        }, $input);
    }

    /**
     * Dispatch plugin event new survey settings
     *
     * @param int $surveyId
     * @param array $pluginSettings
     * @return void
     */
    private function dispatchPluginEventNewSurveySettings($surveyId, $pluginSettings)
    {
        $pluginSettings = is_array($pluginSettings) && !empty($pluginSettings)
            ? $pluginSettings
            : [];
        foreach ($pluginSettings as $plugin => $settings) {
            $settingsEvent = new PluginEvent('newSurveySettings');
            $settingsEvent->set('settings', $settings);
            $settingsEvent->set('survey', $surveyId);
            $this->pluginManager
                ->dispatchEvent($settingsEvent, $plugin);
        }
    }

    /**
     * Dispatch plugin event before survey settings save
     *
     * @param int $surveyId
     * @param array $pluginSettings
     * @return void
     */
    private function dispatchPluginEventBeforeSurveySettingsSave(Survey $survey)
    {
        $event = new PluginEvent('beforeSurveySettingsSave');
        $event->set('modifiedSurvey', $survey);
        $this->pluginManager->dispatchEvent($event);
    }

    /**
     * Format date time input
     *
     * Converts date time string from user local format to internal database format.
     *
     * @param string $inputDateTimeString
     * @return string
     */
    private function formatDateTimeInput($inputDateTimeString)
    {
        $this->yiiApp->loadHelper('surveytranslator');
        $this->yiiApp->loadLibrary('Date_Time_Converter');
        $dateFormat = !empty($this->sessionData['dateformat'])
            ? $this->sessionData['dateformat']
            : 1;
        $formatData = getDateFormatData($dateFormat);
        $dateTimeObj = new Date_Time_Converter(
            $inputDateTimeString,
            $formatData['phpdate'] . ' H:i'
        );
        return $dateTimeObj->convert('Y-m-d H:i:s');
    }

    /**
     * Transcribe from 3 checkboxes to 1 char for captcha usages
     * Uses variables from $_POST and transferred Surveyobject
     *
     * 'A' = All three captcha enabled
     * 'B' = All but save and load
     * 'C' = All but registration
     * 'D' = All but survey access
     * 'X' = Only survey access
     * 'R' = Only registration
     * 'S' = Only save and load
     *
     * 'E' = All inherited
     * 'F' = Inherited save and load + survey access + registration
     * 'G' = Inherited survey access + registration + save and load
     * 'H' = Inherited registration + save and load + survey access
     * 'I' = Inherited save and load + inherited survey access + registration
     * 'J' = Inherited survey access + inherited registration + save and load
     * 'K' = Inherited registration + inherited save and load + survey access
     *
     * 'L' = Inherited survey access + save and load
     * 'M' = Inherited survey access + registration
     * 'O' = Inherited registration + survey access
     * '1' = Inherited survey access + inherited registration
     * '2' = Inherited survey access + inherited save and load
     * '3' = Inherited registration + inherited save and load
     * '4' = Inherited survey access
     * '5' = Inherited save and load
     * '6' = Inherited registration
     *
     * 'N' = None
     *
     * @return ?string One character that corresponds to captcha usage
     * @todo Should really be saved as three fields in the database!
     * @todo Copied from Survey:::saveTranscribeCaptchaOptions() replace uses of original copy
     * @SuppressWarnings("php:S3776") Cognitive Complexity
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function calculateUseCaptchaOption($surveyaccess, $registration, $saveandload)
    {
        $result = 'N';

        if ($surveyaccess === null && $registration === null && $saveandload === null) {
            return null;
        }

        if ($surveyaccess == 'I' && $registration == 'I' && $saveandload == 'I') {
            $result = 'E';
        } elseif ($surveyaccess == 'Y' && $registration == 'Y' && $saveandload == 'I') {
            $result = 'F';
        } elseif ($surveyaccess == 'I' && $registration == 'Y' && $saveandload == 'Y') {
            $result = 'G';
        } elseif ($surveyaccess == 'Y' && $registration == 'I' && $saveandload == 'Y') {
            $result = 'H';
        } elseif ($surveyaccess == 'I' && $registration == 'Y' && $saveandload == 'I') {
            $result = 'I';
        } elseif ($surveyaccess == 'I' && $registration == 'I' && $saveandload == 'Y') {
            $result = 'J';
        } elseif ($surveyaccess == 'Y' && $registration == 'I' && $saveandload == 'I') {
            $result = 'K';
        } elseif ($surveyaccess == 'I' && $saveandload == 'Y') {
            $result = 'L';
        } elseif ($surveyaccess == 'I' && $registration == 'Y') {
            $result = 'M';
        } elseif ($registration == 'I' && $surveyaccess == 'Y') {
            $result = 'O';
        } elseif ($registration == 'I' && $saveandload == 'Y') {
            $result = 'P';
        } elseif ($saveandload == 'I' && $surveyaccess == 'Y') {
            $result = 'T';
        } elseif ($saveandload == 'I' && $registration == 'Y') {
            $result = 'U';
        } elseif ($surveyaccess == 'I' && $registration == 'I') {
            $result = '1';
        } elseif ($surveyaccess == 'I' && $saveandload == 'I') {
            $result = '2';
        } elseif ($registration == 'I' && $saveandload == 'I') {
            $result = '3';
        } elseif ($surveyaccess == 'I') {
            $result = '4';
        } elseif ($saveandload == 'I') {
            $result = '5';
        } elseif ($registration == 'I') {
            $result = '6';
        } elseif ($surveyaccess == 'Y' && $registration == 'Y' && $saveandload == 'Y') {
            $result = 'A';
        } elseif ($surveyaccess == 'Y' && $registration == 'Y') {
            $result = 'B';
        } elseif ($surveyaccess == 'Y' && $saveandload == 'Y') {
            $result = 'C';
        } elseif ($registration == 'Y' && $saveandload == 'Y') {
            $result = 'D';
        } elseif ($surveyaccess == 'Y') {
            $result = 'X';
        } elseif ($registration == 'Y') {
            $result = 'R';
        } elseif ($saveandload == 'Y') {
            $result = 'S';
        }

        return $result;
    }
}

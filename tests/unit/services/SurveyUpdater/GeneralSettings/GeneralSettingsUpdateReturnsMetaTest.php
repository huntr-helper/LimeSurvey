<?php

namespace ls\tests\SurveyUpdater;

use ls\tests\TestBaseClass;

use Survey;
use Permission;
use LSYii_Application;
use Mockery;
use LimeSurvey\PluginManager\PluginManager;
use LimeSurvey\Models\Services\SurveyUpdater\{
    GeneralSettings,
    LanguageConsistency
};

class GeneralSettingsUpdateReturnsMetaTest extends TestBaseClass
{
    public function testUpdateReturnsMetaDate()
    {
        $modelPermission = Mockery::mock(Permission::class)
            ->makePartial();
        $modelPermission->shouldReceive('hasSurveyPermission')
            ->andReturn(true);
        $modelPermission->shouldReceive('hasGlobalPermission')
            ->andReturn(true);

        $survey = Mockery::mock(Survey::class)
            ->makePartial();
        $survey->shouldReceive('save')
            ->andReturn(true);
        $survey->shouldReceive('setAttributes')
            ->passthru();
        $survey->setAttributes([
            'sid' => 1,
            'startdate' => '2023-12-01 00:00:00'
        ]);

        $modelSurvey = Mockery::mock(Survey::class)
            ->makePartial();
        $modelSurvey->shouldReceive('findByPk')
            ->andReturn($survey);

        $yiiApp = Mockery::mock(LSYii_Application::class)
            ->makePartial();
        $yiiApp->session['loginID'] = 9876;

        $pluginManager = Mockery::mock(PluginManager::class)
            ->makePartial();
        $pluginManager->shouldReceive('dispatchEvent')
            ->andReturn(null);

        $languageConsistency = Mockery::mock(LanguageConsistency::class)
            ->makePartial();

        $surveyUpdate = new GeneralSettings(
            $modelPermission,
            $modelSurvey,
            $yiiApp,
            $pluginManager,
            $languageConsistency
        );

        $meta = $surveyUpdate->update(1, [
            'startdate' => '01.01.2024 13:45'
        ]);

        $this->assertIsArray($meta);

        $this->assertIsArray($meta);
        $this->assertArrayHasKey('updateFields', $meta);
        $this->assertIsArray($meta['updateFields']);
        $this->assertContains('startdate', $meta['updateFields']);
    }
}

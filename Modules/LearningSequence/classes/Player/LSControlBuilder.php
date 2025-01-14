<?php

declare(strict_types=1);

use ILIAS\KioskMode\ControlBuilder;
use ILIAS\KioskMode\LocatorBuilder;
use ILIAS\KioskMode\TOCBuilder;
use ILIAS\KioskMode\URLBuilder;

use ILIAS\UI\Factory;
use ILIAS\UI\Component\Signal;

/**
 * Class LSControlBuilder
 */
class LSControlBuilder implements ControlBuilder
{
    const CMD_START_OBJECT = 'start_legacy_obj';
    const CMD_CHECK_CURRENT_ITEM_LP = 'ccilp';

    /**
     * @var Component|null
     */
    protected $exit_control;

    /**
     * @var Component|null
     */
    protected $previous_control;

    /**
     * @var Component|null
     */
    protected $next_control;

    /**
     * @var Component|null
     */
    protected $done_control;

    /**
     * @var Component[]
     */
    protected $controls = [];

    /**
     * @var Component[]
     */
    protected $toggles = [];

    /**
     * @var Component[]
     */
    protected $mode_controls = [];

    /**
     * @var TOCBuilder|null
     */
    protected $toc;

    /**
     * @var LocatorBuilder|null
     */
    protected $loc;

    /**
     * @var Factory
     */
    protected $ui_factory;

    /**
     * @var URLBuilder
     */
    protected $url_builder;

    /**
     * @var Component|null
     */
    protected $start;

    // fau: lsoManualRefresh - variable for refresh control
    /**
     * @var Component|null
     */
    protected $refresh;
    // fau.

    /**
     * @var string | null
     */
    protected $additional_js;

    /**
     * @var LSGlobalSettings
     */
    protected $global_settings;

    public function __construct(
        Factory $ui_factory,
        LSURLBuilder $url_builder,
        ilLanguage $language,
        LSGlobalSettings $global_settings
    ) {
        $this->ui_factory = $ui_factory;
        $this->url_builder = $url_builder;
        $this->lng = $language;
        $this->global_settings = $global_settings;
    }

    public function getExitControl()
    {
        return $this->exit_control;
    }

    public function getPreviousControl()
    {
        return $this->previous_control;
    }

    public function getNextControl()
    {
        return $this->next_control;
    }

    public function getDoneControl()
    {
        return $this->done_control;
    }

    public function getToggles()
    {
        return $this->toggles;
    }

    public function getModeControls()
    {
        return $this->mode_controls;
    }

    public function getControls() : array
    {
        return $this->controls;
    }

    public function getLocator()
    {
        return $this->loc;
    }

    public function getToc()
    {
        return $this->toc;
    }

    /**
     * @inheritdoc
     */
    public function exit(string $command) : ControlBuilder
    {
        if ($this->exit_control) {
            throw new \LogicException("Only one exit-control per view...", 1);
        }
        $cmd = $this->url_builder->getHref($command);

        $label = 'lso_player_suspend';
        if ($command === ilLSPlayer::LSO_CMD_FINISH) {
            $label = 'lso_player_finish';
        }

        $exit_button = $this->ui_factory->button()->bulky(
            $this->ui_factory->symbol()->glyph()->close(),
            $this->lng->txt($label),
            $cmd
        );

        $this->exit_control = $exit_button;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function next(string $command, int $parameter = null) : ControlBuilder
    {
        if ($this->next_control) {
            throw new \LogicException("Only one next-control per view...", 1);
        }
        $label = $this->lng->txt('lso_player_next');
        $cmd = $this->url_builder->getHref($command, $parameter);
        $btn = $this->ui_factory->button()->standard($label, $cmd);
        if ($command === '') {
            $btn = $btn->withUnavailableAction();
        }
        $this->next_control = $btn;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function previous(string $command, int $parameter = null) : ControlBuilder
    {
        if ($this->previous_control) {
            throw new \LogicException("Only one previous-control per view...", 1);
        }
        $label = $this->lng->txt('lso_player_previous');
        $cmd = $this->url_builder->getHref($command, $parameter);
        $btn = $this->ui_factory->button()->standard($label, $cmd);
        if ($command === '') {
            $btn = $btn->withUnavailableAction();
        }
        $this->previous_control = $btn;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function done(string $command, int $parameter = null) : ControlBuilder
    {
        if ($this->done_control) {
            throw new \LogicException("Only one done-control per view...", 1);
        }
        $label = $this->lng->txt('lso_player_done');
        $cmd = $this->url_builder->getHref($command, $parameter);
        $btn = $this->ui_factory->button()->primary($label, $cmd);
        $this->done_control = $btn;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function generic(string $label, string $command, int $parameter = null) : ControlBuilder
    {
        $cmd = $this->url_builder->getHref($command, $parameter);
        $this->controls[] = $this->ui_factory->button()->standard($label, $cmd);
        return $this;
    }

    public function genericWithSignal(string $label, Signal $signal) : ControlBuilder
    {
        $this->controls[] = $this->ui_factory->button()->standard($label, '')
            ->withOnClick($signal);
        return $this;
    }

    /**
     * A toggle can be used to switch some behaviour in the view on or of.
     */
    public function toggle(string $label, string $on_command, string $off_command) : ControlBuilder
    {
        throw new \Exception("NYI: Toggles", 1);

        $cmd_on = $this->url_builder->getHref($on_command, 0);
        $cmd_off = $this->url_builder->getHref($off_command, 0);
        //build toggle and add to $this->toggles
        //return $this;
    }

    /**
     * @inheritdoc
     */
    public function mode(string $command, array $labels) : ControlBuilder
    {
        $actions = [];
        foreach ($labels as $parameter => $label) {
            $actions[$label] = $this->url_builder->getHref($command, $parameter);
        }
        $this->mode_controls[] = $this->ui_factory->viewControl()->mode($actions, '');
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function locator(string $command) : LocatorBuilder
    {
        if ($this->loc) {
            throw new \LogicException("Only one locator per view...", 1);
        }
        $this->loc = new LSLocatorBuilder($command, $this);
        return $this->loc;
    }

    /**
     * @inheritdoc
     */
    public function tableOfContent(
        string $label,
        string $command,
        int $parameter = null,
        $state = null
    ) : TOCBuilder {
        if ($this->toc) {
            throw new \LogicException("Only one ToC per view...", 1);
        }
        $this->toc = new LSTOCBuilder($this, $command, $label, $parameter, $state);
        return $this->toc;
    }

    /**
     * Add a "start"-button as primary.
     * This is NOT regular behavior, but a special feature for the LegacyView
     * of LearningSequence's sub-objects that do not implement a KioskModeView.
     *
     * The start-control is exclusively used to open an ILIAS-Object in a new windwow/tab.
     */
    public function start(string $label, string $url, int $parameter = null) : ControlBuilder
    {
        if ($this->start) {
            throw new \LogicException("Only one start-control per view...", 1);
        }
        $this_cmd = $this->url_builder->getHref(self::CMD_START_OBJECT, $parameter);
        $lp_cmd = str_replace(
            '&cmd=view&',
            '&cmd=' . self::CMD_CHECK_CURRENT_ITEM_LP . '&',
            $this_cmd
        );

        $this->setListenerJS($lp_cmd, $this_cmd);
        $this->start = $this->ui_factory->button()
            ->primary($label, '')
            ->withOnLoadCode(function ($id) use ($url) {
                $interval = $this->global_settings->getPollingIntervalMilliseconds();
                return "$('#{$id}').on('click', function(ev) {
					var il_ls_win = window.open('$url');
					window._lso_current_item_lp = -1;
                    // fau: lsoManualRefresh - don't trigger lso_checkLPOfObject per timer
					   lso_checkLPOfObject();
                    // window.setInterval(lso_checkLPOfObject, $interval);
                    // fau. 
				})";
            });

        return $this;
    }

    public function getStartControl()
    {
        return $this->start;
    }

    // fau: lsoManualRefresh - build controls for a manual refresh
    public function refresh($label) : ControlBuilder
    {
        $this->refresh = $this->ui_factory->button()->standard($label,'javascript:lso_refreshPage();');
        return $this;
    }

    public function getRefreshControl()
    {
        return $this->refresh;
    }
    // fau.

    public function getAdditionalJS() : string
    {
        return $this->additional_js;
    }

    protected function setListenerJS(
        string $check_lp_url,
        string $on_lp_change_url
    ) {
        $this->additional_js =
<<<JS
function lso_checkLPOfObject()
{
    if(! il.UICore.isPageVisible()) {
        return;
    }

	$.ajax({
		url: "$check_lp_url",
	}).done(function(data) {
		if(window._lso_current_item_lp === -1) {
			window._lso_current_item_lp = data;
		}
		if (window._lso_current_item_lp !== data) {
			location.replace('$on_lp_change_url');
		}
	});
}

// fau: lsoManualRefresh - new function to refresh the page
function lso_refreshPage() {
    location.replace('$on_lp_change_url');
}
// fau.

JS;
    }
}

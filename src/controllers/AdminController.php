<?hh // strict

class AdminController extends Controller {
  <<__Override>>
  protected function getTitle(): string {
    return tr('Facebook CTF').' | '.tr('Admin');
  }

  <<__Override>>
  protected function getFilters(): array<string, mixed> {
    return array(
      'GET' => array(
        'page'        => array(
          'filter'      => FILTER_VALIDATE_REGEXP,
          'options'     => array(
            'regexp'      => '/^[\w-]+$/'
          ),
        ),
      )
    );
  }

  <<__Override>>
  protected function getPages(): array<string> {
    return array(
      'main',
      'configuration',
      'controls',
      'announcements',
      'quiz',
      'flags',
      'bases',
      'categories',
      'countries',
      'teams',
      'logos',
      'sessions',
      'scoreboard',
      'logs',
    );
  }

  private async function genGenerateCountriesSelect(
    int $selected,
  ): Awaitable<:xhp> {
    $select = <select class="not_configuration" name="entity_id" disabled={true} />;

    if ($selected === 0) {
      $select->appendChild(<option value="0" selected={true}>{tr('Auto')}</option>);
    } else {
      $country = await Country::gen(intval($selected));
      $select->appendChild(<option value={strval($country->getId())} selected={true}>{$country->getName()}</option>);
    }

    $countries = await Country::genAllAvailableCountries();
    foreach ($countries as $country) {
      $select->appendChild(<option value={strval($country->getId())}>{$country->getName()}</option>);
    }

    return $select;
  }

  private async function genGenerateLevelCategoriesSelect(
    int $selected,
  ): Awaitable<:xhp> {
    $categories = await Category::genAllCategories();
    $select = <select class="not_configuration" name="category_id" disabled={true} />;

    foreach ($categories as $category) {
      if ($category->getCategory() === 'Quiz') {
        continue;
      }

      if ($category->getId() === $selected) {
        $select->appendChild(<option id="category_option" value={strval($category->getId())} selected={true}>{$category->getCategory()}</option>);
      } else {
        $select->appendChild(<option id="category_option" value={strval($category->getId())}>{$category->getCategory()}</option>);
      }
    }

    return $select;
  }

  private async function genGenerateFilterCategoriesSelect(): Awaitable<:xhp> {
    $categories = await Category::genAllCategories();
    $select = <select class="not_configuration" name="category_filter" />;

    $select->appendChild(<option class="filter_option" value="all" selected={true}>{tr('All Categories')}</option>);
    foreach ($categories as $category) {
      if ($category->getCategory() === 'Quiz') {
        continue;
      }
      $select->appendChild(
        <option class="filter_option" value={$category->getCategory()}>
          {$category->getCategory()}
        </option>
      );
    }

    return $select;
  }

  private async function genRegistrationTypeSelect(): Awaitable<:xhp> {
    $config = await Configuration::gen('registration_type');
    $type = $config->getValue();
    $select = <select name="fb--conf--registration_type"></select>;
    $select->appendChild(<option class="fb--conf--registration_type" value="1" selected={($type === '1')}>{tr('Open')}</option>);
    $select->appendChild(<option class="fb--conf--registration_type" value="2" selected={($type === '2')}>{tr('Tokenized')}</option>);

    return $select;
  }

  private async function genConfigurationDurationSelect(): Awaitable<:xhp> {
    $config = await Configuration::gen('game_duration');
    $duration = intval($config->getValue());
    $select = <select name="fb--conf--game_duration"></select>;

    for ($i=1; $i<=24; $i++) {
      $x = 60 * 60 * $i;
      $x_str = ($i > 1) ? ($i . " ". tr('Hours')) : ($i . " " . tr('Hour'));
      $select->appendChild(<option class="fb--conf--game_duration" value={(string)$x} selected={($duration === $x)}>{$x_str}</option>);
    }

    return $select;
  }

  private async function genLanguageSelect(): Awaitable<:xhp> {
    $config = await Configuration::gen('language');
    $current_lang = $config->getValue();
    $available_languages = scandir('language/');
    $select = <select name="fb--conf--language"></select>;
    foreach($available_languages as $file_name){
      $matches = array();
      if(preg_match('/^lang_(.*)\.php$/',$file_name,$matches)){
        $lang = $matches[1];
        $lang_name = locale_get_display_language($lang,$current_lang) . " / " . locale_get_display_language($lang,$lang);
        $select->appendChild(<option class="fb--conf--language" value={$lang} selected={($current_lang === $lang)}>{$lang_name}</option>);
      }
    }
    return $select;
  }

  public async function genRenderConfigurationTokens(): Awaitable<:xhp> {
    $tokens_table = <table></table>;
    $tokens = await Token::genAllTokens();
    foreach($tokens as $token) {
      if ($token->getUsed()) {
        $team = await Team::genTeam($token->getTeamId());
        $token_status = <span class="highlighted--red">{tr('Used by')} {$team->getName()}</span>;
      } else {
        $token_status = <span class="highlighted--green">{tr('Available')}</span>;
      }
      $tokens_table->appendChild(
        <tr>
          <td>{$token->getToken()}</td>
          <td>{$token_status}</td>
        </tr>
      );
    }

    return
      <div class="radio-tab-content" data-tab="reg_tokens">
        <div class="admin-sections">
          <section class="admin-box">
            <header class="admin-box-header">
              <h3>{tr('Registration Tokens')}</h3>
            </header>
            <div class="fb-column-container">
              {$tokens_table}
            </div>
            <div class="admin-buttons admin-row">
              <div class="button-right">
                <button class="fb-cta cta--yellow" data-action="create-tokens">{tr('Create More')}</button>
                <button class="fb-cta cta--yellow" data-action="export-tokens">{tr('Export Available')}</button>
              </div>
            </div>
          </section>
        </div>
      </div>;
  }

  public async function genRenderConfigurationContent(): Awaitable<:xhp> {
    $registration = await Configuration::gen('registration');
    $registration_players = await Configuration::gen('registration_players');
    $login = await Configuration::gen('login');
    $login_select = await Configuration::gen('login_select');
    $login_strongpasswords = await Configuration::gen('login_strongpasswords');
    $registration_names = await Configuration::gen('registration_names');
    $scoring = await Configuration::gen('scoring');
    $gameboard = await Configuration::gen('gameboard');
    $timer = await Configuration::gen('timer');
    $progressive_cycle = await Configuration::gen('progressive_cycle');
    $default_bonus = await Configuration::gen('default_bonus');
    $default_bonusdec = await Configuration::gen('default_bonusdec');
    $bases_cycle = await Configuration::gen('bases_cycle');
    $start_ts = await Configuration::gen('start_ts');
    $end_ts = await Configuration::gen('end_ts');

    $registration_on = $registration->getValue() === '1';
    $registration_off = $registration->getValue() === '0';
    $login_on = $login->getValue() === '1';
    $login_off = $login->getValue() === '0';
    $login_select_on = $login_select->getValue() === '1';
    $login_select_off = $login_select->getValue() === '0';
    $strong_passwords_on = $login_strongpasswords->getValue() === '1';
    $strong_passwords_off = $login_strongpasswords->getValue() === '0';
    $registration_names_on = $registration_names->getValue() === '1';
    $registration_names_off = $registration_names->getValue() === '0';
    $scoring_on = $scoring->getValue() === '1';
    $scoring_off = $scoring->getValue() === '0';
    $gameboard_on = $gameboard->getValue() === '1';
    $gameboard_off = $gameboard->getValue() === '0';
    $timer_on = $timer->getValue() === '1';
    $timer_off = $timer->getValue() === '0';

    if ($start_ts->getValue() === '0') {
      $start_ts = tr('Not started yet');
      $end_ts = tr('Not started yet');
    } else {
      $start_ts = date(tr('date and time format'), $start_ts->getValue());
      $end_ts = date(tr('date and time format'), $end_ts->getValue());
    }

    $registration_type = await Configuration::gen('registration_type');
    if ($registration_type->getValue() === '2') { // Registration is tokenized
      $registration_tokens = await $this->genRenderConfigurationTokens();
      $tabs_conf =
        <div class="radio-tabs">
          <input type="radio" value="reg_conf" name="fb--admin--tabs--conf" id="fb--admin--tabs--conf--conf" checked={true}/>
          <label for="fb--admin--tabs--conf--conf">{tr('Configuration')}</label>
          <input type="radio" value="reg_tokens" name="fb--admin--tabs--conf" id="fb--admin--tabs--conf--tokens"/>
          <label id="fb--admin--tabs--conf--tokens-label" for="fb--admin--tabs--conf--tokens">{tr('Tokens')}</label>
        </div>;
    } else {
      $tabs_conf = <div class="radio-tabs"></div>;
      $registration_tokens = <div></div>;
    }

    $registration_type_select = await $this->genRegistrationTypeSelect();
    $configuration_duration_select = await $this->genConfigurationDurationSelect();
    $language_select = await $this->genLanguageSelect();

    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Game Configuration')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$tabs_conf}
        <div class="tab-content-container">
          <div class="radio-tab-content active" data-tab="reg_conf">
            <div class="admin-sections">
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Registration')}</h3>
                  <div class="admin-section-toggle radio-inline">
                    <input type="radio" name="fb--conf--registration" id="fb--conf--registration--on" checked={$registration_on}/>
                    <label for="fb--conf--registration--on">{tr('On')}</label>
                    <input type="radio" name="fb--conf--registration" id="fb--conf--registration--off" checked={$registration_off}/>
                    <label for="fb--conf--registration--off">{tr('Off')}</label>
                  </div>
                </header>
                <div class="fb-column-container">
                  <div class="col col-pad col-1-4">
                    <div class="form-el el--block-label">
                      <label>{tr('Player Names')}</label>
                      <div class="admin-section-toggle radio-inline">
                        <input type="radio" name="fb--conf--registration_names" id="fb--conf--registration_names--on" checked={$registration_names_on}/>
                        <label for="fb--conf--registration_names--on">{tr('On')}</label>
                        <input type="radio" name="fb--conf--registration_names" id="fb--conf--registration_names--off" checked={$registration_names_off}/>
                        <label for="fb--conf--registration_names--off">{tr('Off')}</label>
                      </div>
                    </div>
                  </div>
                  <div class="col col-pad col-2-4">
                    <div class="form-el el--block-label">
                      <label for="">{tr('Players Per Team')}</label>
                      <input type="number" value={$registration_players->getValue()} name="fb--conf--registration_players" max="12" min="1"/>
                    </div>
                  </div>
                  <div class="col col-pad col-3-4">
                    <div class="form-el el--block-label">
                      <label>{tr('Registration Type')}</label>
                      {$registration_type_select}
                    </div>
                  </div>
                </div>
              </section>
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Login')}</h3>
                  <div class="admin-section-toggle radio-inline">
                    <input type="radio" name="fb--conf--login" id="fb--conf--login--on" checked={$login_on}/>
                    <label for="fb--conf--login--on">{tr('On')}</label>
                    <input type="radio" name="fb--conf--login" id="fb--conf--login--off"checked={$login_off}/>
                    <label for="fb--conf--login--off">{tr('Off')}</label>
                  </div>
                </header>
                <div class="fb-column-container">
                  <div class="col col-pad col-1-2">
                    <div class="form-el el--block-label">
                      <label>{tr('Strong Passwords')}</label>
                      <div class="admin-section-toggle radio-inline">
                        <input type="radio" name="fb--conf--login_strongpasswords" id="fb--conf--login_strongpasswords--on" checked={$strong_passwords_on}/>
                        <label for="fb--conf--login_strongpasswords--on">{tr('On')}</label>
                        <input type="radio" name="fb--conf--login_strongpasswords" id="fb--conf--login_strongpasswords--off" checked={$strong_passwords_off}/>
                        <label for="fb--conf--login_strongpasswords--off">{tr('Off')}</label>
                      </div>
                    </div>
                  </div>
                  <div class="col col-pad col-2-2">
                    <div class="form-el el--block-label">
                      <label>{tr('Team Selection')}</label>
                      <div class="admin-section-toggle radio-inline">
                        <input type="radio" name="fb--conf--login_select" id="fb--conf--login_select--on" checked={$login_select_on}/>
                        <label for="fb--conf--login_select--on">{tr('On')}</label>
                        <input type="radio" name="fb--conf--login_select" id="fb--conf--login_select--off" checked={$login_select_off}/>
                        <label for="fb--conf--login_select--off">{tr('Off')}</label>
                      </div>
                    </div>
                  </div>
                </div>
              </section>
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Game')}</h3>
                </header>
                <div class="fb-column-container">
                  <div class="col col-pad col-1-4">
                    <div class="form-el el--block-label">
                      <label>{tr('Scoring')}</label>
                      <div class="admin-section-toggle radio-inline">
                        <input type="radio" name="fb--conf--scoring" id="fb--conf--scoring--on" checked={$scoring_on}/>
                        <label for="fb--conf--scoring--on">{tr('On')}</label>
                        <input type="radio" name="fb--conf--scoring" id="fb--conf--scoring--off" checked={$scoring_off}/>
                        <label for="fb--conf--scoring--off">{tr('Off')}</label>
                      </div>
                    </div>
                    <div class="form-el el--block-label">
                      <label>{tr('Progressive Cycle (s)')}</label>
                      <input type="number" value={$progressive_cycle->getValue()} name="fb--conf--progressive_cycle"/>
                    </div>
                  </div>
                  <div class="col col-pad col-2-4">
                    <div class="form-el el--block-label">
                      <label>{tr('Refresh Gameboard')}</label>
                      <div class="admin-section-toggle radio-inline">
                        <input type="radio" name="fb--conf--gameboard" id="fb--conf--gameboard--on" checked={$gameboard_on}/>
                        <label for="fb--conf--gameboard--on">{tr('On')}</label>
                        <input type="radio" name="fb--conf--gameboard" id="fb--conf--gameboard--off"checked={$gameboard_off}/>
                        <label for="fb--conf--gameboard--off">{tr('Off')}</label>
                      </div>
                    </div>
                    <div class="form-el el--block-label">
                      <label>{tr('Default Bonus')}</label>
                      <input type="number" value={$default_bonus->getValue()} name="fb--conf--default_bonus"/>
                    </div>
                  </div>
                  <div class="col col-pad col-3-4">
                    <div class="form-el el--block-label">
                      <label>{tr('Bases Cycle (s)')}</label>
                      <input type="number" value={$bases_cycle->getValue()} name="fb--conf--bases_cycle"/>
                    </div>
                    <div class="form-el el--block-label">
                      <label>{tr('Default Bonus Dec')}</label>
                      <input type="number" value={$default_bonusdec->getValue()} name="fb--conf--default_bonusdec"/>
                    </div>
                  </div>
                  <div class="col col-pad col-4-4">
                    <div class="form-el el--block-label">

                    </div>
                  </div>
                </div>
              </section>
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Timer')}</h3>
                  <div class="admin-section-toggle radio-inline">
                    <input type="radio" name="fb--conf--timer" id="fb--conf--timer--on" checked={$timer_on}/>
                    <label for="fb--conf--timer--on">{tr('On')}</label>
                    <input type="radio" name="fb--conf--timer" id="fb--conf--timer--off" checked={$timer_off}/>
                    <label for="fb--conf--timer--off">{tr('Off')}</label>
                  </div>
                </header>
                <div class="fb-column-container">
                  <div class="col col-pad col-1-4">
                    <div class="form-el el--block-label el--full-text">
                      <label for="">{tr('Server Time')}</label>
                      <input type="text" value={date(tr('date and time format'), time())} name="fb--conf--server_time" disabled={true}/>
                    </div>
                  </div>
                  <div class="col col-pad col-2-4">
                    <div class="form-el el--block-label el--full-text">
                      <label for="">{tr('Game Duration')}</label>
                      {$configuration_duration_select}
                    </div>
                  </div>
                  <div class="col col-pad col-2-4">
                    <div class="form-el el--block-label el--full-text">
                      <label for="">{tr('Begin Time')}</label>
                      <input type="text" value={$start_ts} id="fb--conf--start_ts" disabled={true}/>
                    </div>
                  </div>
                  <div class="col col-pad col-3-4">
                    <div class="form-el el--block-label el--full-text">
                      <label for="">{tr('Expected End Time')}</label>
                      <input type="text" value={$end_ts} id="fb--conf--end_ts" disabled={true}/>
                    </div>
                  </div>
                </div>
              </section>
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Language')}</h3>
                </header>
                  <div class="col col-pad col-1-2">
                    <div class="form-el el--block-label el--full-text">
                      {$language_select}
                    </div>
                  </div>
              </section>
            </div>
          </div>
          {$registration_tokens}
        </div>
      </div>;
  }

  public async function genRenderAnnouncementsContent(): Awaitable<:xhp> {
    $announcements = await Announcement::genAllAnnouncements();
    $announcements_div = <div></div>;
    if ($announcements) {
      foreach ($announcements as $announcement) {
        $announcements_div->appendChild(
          <section class="admin-box">
            <form class="announcements_form">
              <input type="hidden" name="announcement_id" value={strval($announcement->getId())}/>
              <header class="management-header">
                <h6>{time_ago($announcement->getTs())}</h6>
                <a class="highlighted--red" href="#" data-action="delete">{tr('DELETE')}</a>
              </header>
              <div class="fb-column-container">
                <div class="col col-pad">
                  <div class="selected-logo">
                    <span class="logo-name">{$announcement->getAnnouncement()}</span>
                  </div>
                </div>
              </div>
            </form>
          </section>
        );
      }
    } else {
      $announcements_div->appendChild(
        <section class="admin-box">
          <div class="fb-column-container">
            <div class="col col-pad">
              <div class="selected-logo-text">
                <span class="logo-name">{tr('No Announcements')}</span>
              </div>
            </div>
          </div>
        </section>
      );
    }
    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Game Controls')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        <div class="admin-sections">
          <section class="admin-box">
            <header class="admin-box-header">
              <h3>{tr('Announcements')}</h3>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-3-4">
                <div class="form-el el--block-label el--full-text">
                  <input type="text" name="new_announcement" placeholder={tr('Write New Announcement here')} value=""/>
                </div>
              </div>
              <div class="col col-pad col-1-4">
                <div class="form-el el--block-label el--full-text">
                  <div class="admin-buttons">
                    <button class="fb-cta cta--yellow" data-action="create-announcement">{tr('Create')}</button>
                  </div>
                </div>
              </div>
            </div>
          </section>
          {$announcements_div}
        </div>
      </div>;
  }

  public function renderControlsContent(): :xhp {
    return
      <div>
        <header class="admin-page-header">
          <h3>Game Controls</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        <div class="admin-sections">
          <section class="admin-box">
            <header class="admin-box-header">
              <h3>{tr('General')}</h3>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-3">
                <div class="form-el el--block-label el--full-text">
                  <div class="admin-buttons">
                    <button class="fb-cta cta--yellow" data-action="backup-db">{tr('Back Up Database')}</button>
                  </div>
                </div>
              </div>
              <div class="col col-pad col-1-3">
                <div class="form-el el--block-label el--full-text">
                  <div class="admin-buttons">
                    <button class="fb-cta cta--yellow" data-action="export-game">{tr('Export Game')}</button>
                  </div>
                </div>
              </div>
              <div class="col col-pad col-1-3">
                <div class="form-el el--block-label el--full-text">
                  <div class="admin-buttons">
                    <button class="fb-cta cta--yellow" data-action="import-game">{tr('Import Game')}</button>
                  </div>
                </div>
              </div>
            </div>
          </section>
          <section class="admin-box">
            <header class="admin-box-header">
              <h3>{tr('Teams')}</h3>
            </header>
            <div class="fb-column-container">
            </div>
          </section>
          <section class="admin-box">
            <header class="admin-box-header">
              <h3>{tr('Levels')}</h3>
            </header>
            <div class="fb-column-container">
            </div>
          </section>
        </div>
      </div>;
  }

  public async function genRenderQuizContent(): Awaitable<:xhp> {
    $countries_select = await $this->genGenerateCountriesSelect(0);


    $hint_div =
      <div class="hints">
        <div class "new-hint new-hint-hidden completely-hidden">
          <div class="form-el fb-column-container col-gutters">
            <div class="col col-2-3 el--block-label el--full-text">
              <label>Hint</label>
              <input name="hint" type="text"/>
            </div>
            <div class="col col-1-3 el--block-label el--full-text">
              <label>Hint Penalty</label>
              <input name="penalty" type="text"/>
            </div>
          </div>
        </div>
      </div>;

    $adminsections =
      <div class="admin-sections">
        <section id="new-element" class="validate-form admin-box completely-hidden">
          <form class="level_form quiz_form">
            <input type="hidden" name="level_type" value="quiz"/>
            <header class="admin-box-header">
              <h3>{tr('New Quiz Level')}</h3>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Title')}</label>
                  <input name="title" type="text" placeholder={tr('Level title')}/>
                </div>
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Question')}</label>
                  <textarea name="question" placeholder={tr('Quiz question')} rows={4} ></textarea>
                </div>
                <div class="form-el el--block-label el--full-text">
                  <label for="">{tr('Country')}</label>
                  {$countries_select}
                </div>
              </div>
              <div class="col col-pad col-1-2">
                <div class="form-el fb-column-container col-gutters">
                  <div class="form-el--required col col-2-3 el--block-label el--full-text">
                    <label>{tr('Answer')}</label>
                    <input name="answer" type="text"/>
                  </div>
                  <div class="form-el--required col col-1-3 el--block-label el--full-text">
                    <label>{tr('Points')}</label>
                    <input name="points" type="text"/>
                  </div>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="col col-2-3 el--block-label el--full-text">
                    <label>{tr('Hint')}</label>
                    <input name="hint" type="text"/>
                  </div>
                  <div class="col col-1-3 el--block-label el--full-text">
                    <label>{tr('Hint Penalty')}</label>
                    <input name="penalty" type="text"/>
                  </div>
                </div><br/>
                {$hint_div}<br/>
                <div class="admin-buttons">
                  <button class="fb-cta" data-action="add-hint">Add Hint</button>
                </div>
              </div>
            </div>
          <div class="admin-buttons admin-row">
            <div class="button-right">
              <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
              <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
              <button class="fb-cta cta--yellow" data-action="create">{tr('Create')}</button>
            </div>
          </div>
        </form>
      </section>
      <section id="new-element" class="admin-box">
        <header class="admin-box-header">
          <h3>{tr('All Quiz Levels')}</h3>
          <form class="all_quiz_form">
            <div class="admin-section-toggle radio-inline col">
              <input type="radio" name="fb--levels--all_quiz" id="fb--levels--all_quiz--on"/>
              <label for="fb--levels--all_quiz--on">{tr('On')}</label>
              <input type="radio" name="fb--levels--all_quiz" id="fb--levels--all_quiz--off"/>
              <label for="fb--levels--all_quiz--off">{tr('Off')}</label>
            </div>
          </form>
        </header>
        <header class="admin-box-header">
          <h3>{tr('Filter By:')}</h3>
          <div class="form-el fb-column-container col-gutters">
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
              <select class="not_configuration" name="status_filter">
                <option class="filter_option" value="all">{tr('All Status')}</option>
                <option class="filter_option" value="Enabled">{tr('Enabled')}</option>
                <option class="filter_option" value="Disabled">{tr('Disabled')}</option>
              </select>
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
          </div>
        </header>
      </section>
    </div>;

    $c = 1;
    $quizes = await Level::genAllQuizLevels();

    $all_hints = await HintLog::genAllHints();
    foreach ($all_hints as $hint) {
      $hint_div->appendChild(
        <div class="form-el fb-column-container col-gutters">
          <div class="col col-2-3 el--block-label el--full-text">
            <label>Hint</label>
            <input name="hint" type="text" value={$flag->getHint()} disabled={true}/>
          </div>
          <div class="col col-1-3 el--block-label el--full-text">
            <label>Hint Penalty</label>
            <input name="penalty" type="text" value={strval($flag->getPenalty())} disabled={true}/>
          </div>
        </div>;
      )
    }

    foreach ($quizes as $quiz) {
      $quiz_active_on = ($quiz->getActive());
      $quiz_active_off = (!$quiz->getActive());

      $quiz_status_name = 'fb--levels--level-'.strval($quiz->getId()).'-status';
      $quiz_status_on_id = 'fb--levels--level-'.strval($quiz->getId()).'-status--on';
      $quiz_status_off_id = 'fb--levels--level-'.strval($quiz->getId()).'-status--off';

      $quiz_id = 'quiz_id'.strval($quiz->getId());

      $countries_select = await $this->genGenerateCountriesSelect($quiz->getEntityId());

      $adminsections->appendChild(
        <section class="admin-box validate-form section-locked">
          <form class="level_form quiz_form" name={$quiz_id}>
            <input type="hidden" name="level_type" value="quiz"/>
            <input type="hidden" name="level_id" value={strval($quiz->getId())}/>
            <header class="admin-box-header">
              <h3>{tr('Quiz Level')} {$c}</h3>
              <div class="admin-section-toggle radio-inline">
                <input type="radio" name={$quiz_status_name} id={$quiz_status_on_id} checked={$quiz_active_on}/>
                <label for={$quiz_status_on_id}>{tr('On')}</label>
                <input type="radio" name={$quiz_status_name} id={$quiz_status_off_id} checked={$quiz_active_off}/>
                <label for={$quiz_status_off_id}>{tr('Off')}</label>
              </div>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Title')}</label>
                  <input name="title" type="text" value={$quiz->getTitle()} disabled={true}/>
                </div>
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Question')}</label>
                  <textarea name="question" rows={6} disabled={true}>{$quiz->getDescription()}</textarea>
                </div>
                <div class="form-el el--block-label el--full-text">
                  <label for="">{tr('Country')}</label>
                  {$countries_select}
                </div>
              </div>
              <div class="col col-pad col-1-2">
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Answer')}</label>
                  <input name="answer" type="password" value={$quiz->getFlag()} disabled={true}/>
                  <a href="" class="toggle_answer_visibility">{tr('Show Answer')}</a>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="form-el--required col col-1-3 el--block-label el--full-text">
                    <label>{tr('Points')}</label>
                    <input name="points" type="text" value={strval($quiz->getPoints())} disabled={true}/>
                  </div>
                  <div class="form-el--required col col-1-3 el--block-label el--full-text">
                    <label>{tr('Bonus')}</label>
                    <input name="bonus" type="text" value={strval($quiz->getBonus())} disabled={true}/>
                  </div>
                  <div class="form-el--required col col-1-3 el--block-label el--full-text">
                    <label>{tr('-Dec')}</label>
                    <input name="bonus_dec" type="text" value={strval($quiz->getBonusDec())} disabled={true}/>
                  </div>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="col col-2-3 el--block-label el--full-text">
                    <label>{tr('Hint')}</label>
                    <input name="hint" type="text" value={$quiz->getHint()} disabled={true}/>
                  </div>
                  <div class="col col-1-3 el--block-label el--full-text">
                    <label>{tr('Hint Penalty')}</label>
                    <input name="penalty" type="text" value={strval($quiz->getPenalty())} disabled={true}/>
                  </div>
                </div>
              </div>
            </div>
            <div class="admin-buttons admin-row">
              <div class="button-right">
                <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
                <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
                <button class="fb-cta cta--yellow" data-action="save">{tr('Save')}</button>
              </div>
            </div>
          </form>
        </section>
      );
      $c++;
    }

    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Quiz Management')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
        <div class="admin-buttons">
          <button class="fb-cta" data-action="add-new">{tr('Add Quiz Level')}</button>
        </div>
      </div>;
  }

  public async function genRenderFlagsContent(): Awaitable<:xhp> {
    $countries_select = await $this->genGenerateCountriesSelect(0);
    $level_categories_select = await $this->genGenerateLevelCategoriesSelect(0);
    $filter_categories_select = await $this->genGenerateFilterCategoriesSelect();

    $adminsections =
      <div class="admin-sections">
        <section id="new-element" class="validate-form admin-box completely-hidden">
          <form class="level_form flag_form">
            <input type="hidden" name="level_type" value="flag"/>
            <header class="admin-box-header">
              <h3>{tr('New Flag Level')}</h3>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Title')}</label>
                  <input name="title" type="text" placeholder={tr('Level title')}/>
                </div>
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Description')}</label>
                  <textarea name="description" placeholder={tr('Level description')} rows={4}></textarea>
                </div>
                <div class="form-el form-el--required fb-column-container col-gutters">
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label for="">{tr('Country')}</label>
                    {$countries_select}
                  </div>
              <div class="col col-1-2 el--block-label el--full-text">
                <label for="">{tr('Category')}</label>
                {$level_categories_select}
              </div>
            </div>
          </div>
          <div class="col col-pad col-1-2">
            <div class="form-el fb-column-container col-gutters">
              <div class="form-el--required col col-2-3 el--block-label el--full-text">
                <label>{tr('Flag')}</label>
                <input name="flag" type="text"/>
              </div>
              <div class="form-el--required col col-1-3 el--block-label el--full-text">
                <label>{tr('Points')}</label>
                <input name="points" type="text"/>
              </div>
            </div>
            <div class="form-el fb-column-container col-gutters">
              <div class="col col-2-3 el--block-label el--full-text">
                <label>{tr('Hint')}</label>
                <input name="hint" type="text"/>
              </div>
              <div class="col col-1-3 el--block-label el--full-text">
                <label>{tr('Hint Penalty')}</label>
                <input name="penalty" type="text"/>
              </div>
            </div>
          </div>
        </div>
        <div class="admin-buttons admin-row">
          <div class="button-right">
            <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
            <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
            <button class="fb-cta cta--yellow" data-action="create">{tr('Create')}</button>
          </div>
        </div>
        </form>
      </section>
      <section id="new-element" class="admin-box">
        <header class="admin-box-header">
          <h3>{tr('All Flag Levels')}</h3>
          <form class="all_flag_form">
            <div class="admin-section-toggle radio-inline col">
              <input type="radio" name="fb--levels--all_flag" id="fb--levels--all_flag--on"/>
              <label for="fb--levels--all_flag--on">{tr('On')}</label>
              <input type="radio" name="fb--levels--all_flag" id="fb--levels--all_flag--off"/>
              <label for="fb--levels--all_flag--off">{tr('Off')}</label>
            </div>
          </form>
        </header>
        <header class="admin-box-header">
          <h3>{tr('Filter By:')}</h3>
          <div class="form-el fb-column-container col-gutters">
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
              {$filter_categories_select}
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
              <select class="not_configuration" name="status_filter">
                <option class="filter_option" value="all">{tr('All Status')}</option>
                <option class="filter_option" value="Enabled">{tr('Enabled')}</option>
                <option class="filter_option" value="Disabled">{tr('Disabled')}</option>
              </select>
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
          </div>
        </header>
      </section>
    </div>;

    $c = 1;
    $flags = await Level::genAllFlagLevels();
    foreach ($flags as $flag) {
      $flag_active_on = ($flag->getActive());
      $flag_active_off = (!$flag->getActive());

      $flag_status_name = 'fb--levels--level-'.strval($flag->getId()).'-status';
      $flag_status_on_id = 'fb--levels--level-'.strval($flag->getId()).'-status--on';
      $flag_status_off_id = 'fb--levels--level-'.strval($flag->getId()).'-status--off';

      $flag_id = 'flag_id'.strval($flag->getId());

      $attachments_div =
        <div class="attachments">
          <div class="new-attachment new-attachment-hidden fb-column-container completely-hidden">
            <div class="col col-pad col-1-3">
              <div class="form-el">
                <form class="attachment_form">
                  <input type="hidden" name="action" value="create_attachment"/>
                  <input type="hidden" name="level_id" value={strval($flag->getId())}/>
                  <div class="col el--block-label el--full-text">
                    <label>{tr('New Attachment:')}</label>
                    <input name="filename" type="text"/>
                    <input name="attachment_file" type="file"/>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-buttons col col-pad col-1-3">
              <div class="col el--block-label el--full-text">
                <button class="fb-cta cta--red" data-action="delete-new-attachment">X</button>
                <button class="fb-cta cta--yellow" data-action="create-attachment">{tr('Create')}</button>
              </div>
            </div>
          </div>
        </div>;

      $attachments = await Attachment::genHasAttachments($flag->getId());
      if ($attachments) {
        $a_c = 1;
        $all_attachments = await Attachment::genAllAttachments($flag->getId());
        foreach ($all_attachments as $attachment) {
          $attachments_div->appendChild(
            <div class="existing-attachment fb-column-container">
              <div class="col col-pad col-2-3">
                <div class="form-el">
                  <form class="attachment_form">
                    <input type="hidden" name="attachment_id" value={strval($attachment->getId())}/>
                    <div class="col el--block-label el--full-text">
                      <label>{tr('Attachment')} {$a_c}:</label>
                      <input name="filename" type="text" value={$attachment->getFilename()} disabled={true}/>
                      <a href={$attachment->getFilename()} target="_blank">{tr('Link')}</a>
                    </div>
                  </form>
                </div>
              </div>
              <div class="admin-buttons col col-pad col-1-3">
                <div class="col el--block-label el--full-text">
                  <button class="fb-cta cta--red" data-action="delete-attachment">X</button>
                </div>
              </div>
            </div>
          );
          $a_c++;
        }
      }

      $links_div =
        <div class="links">
          <div class="new-link new-link-hidden fb-column-container completely-hidden">
            <div class="col col-pad col-1-3">
              <div class="form-el">
                <form class="link_form">
                  <input type="hidden" name="action" value="create_link"/>
                  <input type="hidden" name="level_id" value={strval($flag->getId())}/>
                  <div class="col el--block-label el--full-text">
                    <label>{tr('New Link:')}</label>
                    <input name="link" type="text"/>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-buttons col col-pad col-1-3">
              <div class="col el--block-label el--full-text">
                <button class="fb-cta cta--red" data-action="delete-new-link">X</button>
                <button class="fb-cta cta--yellow" data-action="create-link">{tr('Create')}</button>
              </div>
            </div>
          </div>
        </div>;

      $links = await Link::genHasLinks($flag->getId());
      if ($links) {
        $l_c = 1;
        $all_links = await Link::genAllLinks($flag->getId());
        foreach ($all_links as $link) {
          $links_div->appendChild(
            <div class="existing-link fb-column-container">
              <div class="col col-pad col-2-3">
                <div class="form-el">
                  <form class="link_form">
                    <input type="hidden" name="link_id" value={strval($link->getId())}/>
                    <div class="col el--block-label el--full-text">
                      <label>{tr('Link')} {$l_c}:</label>
                      <input name="link" type="text" value={$link->getLink()} disabled={true}/>
                      <a href={$link->getLink()} target="_blank">{tr('Link')}</a>
                    </div>
                  </form>
                </div>
              </div>
              <div class="admin-buttons col col-pad col-1-3">
                <div class="col el--block-label el--full-text">
                  <button class="fb-cta cta--red" data-action="delete-link">X</button>
                </div>
              </div>
            </div>
          );
          $l_c++;
        }
      }

      $countries_select = await $this->genGenerateCountriesSelect($flag->getEntityId());
      $level_categories_select = await $this->genGenerateLevelCategoriesSelect($flag->getCategoryId());

      $adminsections->appendChild(
        <section class="validate-form admin-box section-locked">
          <form class="level_form flag_form" name={$flag_id}>
            <input type="hidden" name="level_type" value="flag"/>
            <input type="hidden" name="level_id" value={strval($flag->getId())}/>
            <header class="admin-box-header">
              <h3>{tr('Flag Level')} {$c}</h3>
              <div class="admin-section-toggle radio-inline">
                <input type="radio" name={$flag_status_name} id={$flag_status_on_id} checked={$flag_active_on}/>
                <label for={$flag_status_on_id}>{tr('On')}</label>
                <input type="radio" name={$flag_status_name} id={$flag_status_off_id} checked={$flag_active_off}/>
                <label for={$flag_status_off_id}>{tr('Off')}</label>
              </div>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Title')}</label>
                  <input name="title" type="text" value={$flag->getTitle()} disabled={true}/>
                </div>
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Description')}</label>
                  <textarea name="description" rows={6} disabled={true}>{$flag->getDescription()}</textarea>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label for="">{tr('Country')}</label>
                    {$countries_select}
                  </div>
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label for="">{tr('Categories')}</label>
                    {$level_categories_select}
                  </div>
                </div>
              </div>
              <div class="col col-pad col-1-2">
                <div class="form-el fb-column-container col-gutters">
                  <div class="form-el--required col el--block-label el--full-text">
                    <label>{tr('Flag')}</label>
                    <input name="flag" type="password" value={$flag->getFlag()} disabled={true}/>
                    <a href="" class="toggle_answer_visibility">{tr('Show Answer')}</a>
                  </div>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="form-el--required col col-1-3 el--block-label el--full-text">
                    <label>{tr('Points')}</label>
                    <input name="points" type="text" value={strval($flag->getPoints())} disabled={true}/>
                  </div>
                  <div class="form-el--required col col-1-3 el--block-label el--full-text">
                    <label>{tr('Bonus')}</label>
                    <input name="bonus" type="text" value={strval($flag->getBonus())} disabled={true}/>
                  </div>
                  <div class="form-el--required col col-1-3 el--block-label el--full-text">
                    <label>{tr('-Dec')}</label>
                    <input name="bonus_dec" type="text" value={strval($flag->getBonusDec())} disabled={true}/>
                  </div>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="col col-2-3 el--block-label el--full-text">
                    <label>{tr('Hint')}</label>
                    <input name="hint" type="text" value={$flag->getHint()} disabled={true}/>
                  </div>
                  <div class="col col-1-3 el--block-label el--full-text">
                    <label>{tr('Hint Penalty')}</label>
                    <input name="penalty" type="text" value={strval($flag->getPenalty())} disabled={true}/>
                  </div>
                </div>
              </div>
            </div>
          </form>
          {$attachments_div}
          {$links_div}
          <div class="admin-buttons admin-row">
            <div class="button-right">
              <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
              <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
              <button class="fb-cta cta--yellow" data-action="save">{tr('Save')}</button>
            </div>
            <div class="button-left">
              <button class="fb-cta" data-action="add-attachment">{tr('+ Attachment')}</button>
              <button class="fb-cta" data-action="add-link">{tr('+ Link')}</button>
            </div>
          </div>
        </section>
      );
      $c++;
    }

    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Flags Management')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
        <div class="admin-buttons">
          <button class="fb-cta" data-action="add-new">{tr('Add Flag Level')}</button>
        </div>
      </div>;
  }

  public async function genRenderBasesContent(): Awaitable<:xhp> {
    $countries_select = await $this->genGenerateCountriesSelect(0);
    $level_categories_select = await $this->genGenerateLevelCategoriesSelect(0);
    $filter_categories_select = await $this->genGenerateFilterCategoriesSelect();

    $adminsections =
      <div class="admin-sections">
        <section id="new-element" class="validate-form admin-box completely-hidden">
          <form class="level_form base_form">
            <input type="hidden" name="level_type" value="base"/>
            <header class="admin-box-header">
              <h3>{tr('New Base Level')}</h3>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Title')}</label>
                  <input name="title" type="text" placeholder={tr('Level title')}/>
                </div>
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Description')}</label>
                  <textarea name="description" placeholder={tr('Level description')} rows={4}></textarea>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label for="">{tr('Country')}</label>
                    {$countries_select}
                  </div>
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label for="">{tr('Category')}</label>
                    {$level_categories_select}
                  </div>
                </div>
              </div>
              <div class="col col-pad col-1-2">
                <div class="form-el fb-column-container col-gutters">
                  <div class="form-el--required col col-1-2 el--block-label el--full-text">
                    <label>{tr('Keep Points')}</label>
                    <input name="points" type="text"/>
                  </div>
                  <div class="form-el--required col col-1-2 el--block-label el--full-text">
                    <label>{tr('Capture points')}</label>
                    <input name="bonus" type="text"/>
                  </div>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="col col-2-3 el--block-label el--full-text">
                    <label>{tr('Hint')}</label>
                  <input name="hint" type="text"/>
                  </div>
                  <div class="col col-1-3 el--block-label el--full-text">
                    <label>{tr('Hint Penalty')}</label>
                    <input name="penalty" type="text"/>
                  </div>
                </div>
              </div>
            </div>
            <div class="admin-buttons admin-row">
              <div class="button-right">
                <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
                <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
                <button class="fb-cta cta--yellow" data-action="create">{tr('Create')}</button>
              </div>
            </div>
          </form>
        </section>
        <section id="new-element" class="admin-box">
          <header class="admin-box-header">
            <h3>{tr('All Base Levels')}</h3>
            <form class="all_base_form">
              <div class="admin-section-toggle radio-inline col">
                <input type="radio" name="fb--levels--all_base" id="fb--levels--all_base--on"/>
                <label for="fb--levels--all_base--on">{tr('On')}</label>
                <input type="radio" name="fb--levels--all_base" id="fb--levels--all_base--off"/>
                <label for="fb--levels--all_base--off">{tr('Off')}</label>
              </div>
            </form>
          </header>
          <header class="admin-box-header">
            <h3>{tr('Filter By:')}</h3>
            <div class="form-el fb-column-container col-gutters">
              <div class="col col-1-5 el--block-label el--full-text">
              </div>
              <div class="col col-1-5 el--block-label el--full-text">
                {$filter_categories_select}
              </div>
              <div class="col col-1-5 el--block-label el--full-text">
              </div>
              <div class="col col-1-5 el--block-label el--full-text">
                <select class="not_configuration" name="status_filter">
                  <option class="filter_option" value="all">{tr('All Status')}</option>
                  <option class="filter_option" value="Enabled">{tr('Enabled')}</option>
                  <option class="filter_option" value="Disabled">{tr('Disabled')}</option>
                </select>
              </div>
              <div class="col col-1-5 el--block-label el--full-text">
              </div>
            </div>
          </header>
        </section>
      </div>;

    $c = 1;
    $all_base_levels = await Level::genAllBaseLevels();
    foreach ($all_base_levels as $base) {
      $base_active_on = ($base->getActive());
      $base_active_off = (!$base->getActive());

      $base_status_name = 'fb--levels--level-'.strval($base->getId()).'-status';
      $base_status_on_id = 'fb--levels--level-'.strval($base->getId()).'-status--on';
      $base_status_off_id = 'fb--levels--level-'.strval($base->getId()).'-status--off';

      $base_id = 'base_id'.strval($base->getId());

      $attachments_div =
        <div class="attachments">
          <div class="new-attachment new-attachment-hidden fb-column-container completely-hidden">
            <div class="col col-pad col-1-3">
              <div class="form-el">
                <form class="attachment_form">
                  <input type="hidden" name="action" value="create_attachment"/>
                  <input type="hidden" name="level_id" value={strval($base->getId())}/>
                  <div class="col el--block-label el--full-text">
                    <label>{tr('New Attachment:')}</label>
                    <input name="filename" type="text"/>
                    <input name="attachment_file" type="file"/>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-buttons col col-pad col-1-3">
              <div class="col el--block-label el--full-text">
                <button class="fb-cta cta--red" data-action="delete-new-attachment">X</button>
                <button class="fb-cta cta--yellow" data-action="create-attachment">{tr('Create')}</button>
              </div>
            </div>
          </div>
        </div>;
      $has_attachments = await Attachment::genHasAttachments($base->getId());
      if ($has_attachments) {
        $a_c = 1;
        $all_attachments = await Attachment::genAllAttachments($base->getId());
        foreach ($all_attachments as $attachment) {
          $attachments_div->appendChild(
            <div class="existing-attachment fb-column-container">
              <div class="col col-pad col-2-3">
                <div class="form-el">
                  <form class="attachment_form">
                    <input type="hidden" name="attachment_id" value={strval($attachment->getId())}/>
                    <div class="col el--block-label el--full-text">
                      <label>{tr('Attachment')} {$a_c}:</label>
                      <input name="filename" type="text" value={$attachment->getFilename()} disabled={true}/>
                      <a href={$attachment->getFilename()} target="_blank">{tr('Link')}</a>
                    </div>
                  </form>
                </div>
              </div>
              <div class="admin-buttons col col-pad col-1-3">
                <div class="col el--block-label el--full-text">
                  <button class="fb-cta cta--red" data-action="delete-attachment">X</button>
                </div>
              </div>
            </div>
          );
        }
        $a_c++;
      }

      $links_div =
        <div class="links">
          <div class="new-link new-link-hidden fb-column-container completely-hidden">
            <div class="col col-pad col-1-3">
              <div class="form-el">
                <form class="link_form">
                  <input type="hidden" name="action" value="create_link"/>
                  <input type="hidden" name="level_id" value={strval($base->getId())}/>
                  <div class="col el--block-label el--full-text">
                    <label>{tr('New Link:')}</label>
                    <input name="link" type="text"/>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-buttons col col-pad col-1-3">
              <div class="col el--block-label el--full-text">
                <button class="fb-cta cta--red" data-action="delete-new-link">X</button>
                <button class="fb-cta cta--yellow" data-action="create-link">{tr('Create')}</button>
              </div>
            </div>
          </div>
        </div>;

      $has_links = await Link::genHasLinks($base->getId());
      if ($has_links) {
        $l_c = 1;
        $all_links = await Link::genAllLinks($base->getId());
        foreach ($all_links as $link) {
          if (filter_var($link->getLink(), FILTER_VALIDATE_URL)) {
            $link_a = <a href={$link->getLink()} target="_blank">{tr('Link')}</a>;
          } else {
            $link_a = <a></a>;
          }
          $links_div->appendChild(
            <div class="existing-link fb-column-container">
              <div class="col col-pad col-2-3">
                <div class="form-el">
                  <form class="link_form">
                    <input type="hidden" name="link_id" value={strval($link->getId())}/>
                    <div class="col el--block-label el--full-text">
                      <label>{tr('Link')} {$l_c}:</label>
                        <input name="link" type="text" value={$link->getLink()} disabled={true}/>
                        {$link_a}
                    </div>
                  </form>
                </div>
              </div>
              <div class="admin-buttons col col-pad col-1-3">
                <div class="col el--block-label el--full-text">
                  <button class="fb-cta cta--red" data-action="delete-link">X</button>
                </div>
              </div>
            </div>
          );
        }
        $l_c++;
      }

      $countries_select = await $this->genGenerateCountriesSelect($base->getEntityId());
      $level_categories_select = await $this->genGenerateLevelCategoriesSelect($base->getCategoryId());

      $adminsections->appendChild(
        <section class="validate-form admin-box section-locked">
          <form class="level_form base_form" name={$base_id}>
            <input type="hidden" name="level_type" value="base"/>
            <input type="hidden" name="level_id" value={strval($base->getId())}/>
            <header class="admin-box-header">
              <h3>{tr('Base Level')} {$c}</h3>
              <div class="admin-section-toggle radio-inline">
                <input type="radio" name={$base_status_name} id={$base_status_on_id} checked={$base_active_on}/>
                <label for={$base_status_on_id}>{tr('On')}</label>
                <input type="radio" name={$base_status_name} id={$base_status_off_id} checked={$base_active_off}/>
                <label for={$base_status_off_id}>{tr('Off')}</label>
              </div>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Title')}</label>
                  <input name="title" type="text" value={$base->getTitle()} disabled={true}/>
                </div>
                <div class="form-el form-el--required el--block-label el--full-text">
                  <label>{tr('Description')}</label>
                  <textarea name="description" rows={4} disabled={true}>{$base->getDescription()}</textarea>
                </div>
                <div class="form-el form-el--required fb-column-container col-gutters">
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label for="">{tr('Country')}</label>
                    {$countries_select}
                  </div>
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label for="">{tr('Category')}</label>
                    {$level_categories_select}
                  </div>
                </div>
              </div>
              <div class="col col-pad col-1-2">
                <div class="form-el fb-column-container col-gutters">
                  <div class="form-el--required col col-1-2 el--block-label el--full-text">
                    <label>{tr('Points')}</label>
                    <input name="points" type="text" value={strval($base->getPoints())} disabled={true}/>
                  </div>
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label>{tr('Bonus')}</label>
                    <input name="bonus" type="text" value={strval($base->getBonus())} disabled={true}/>
                  </div>
                </div>
                <div class="form-el fb-column-container col-gutters">
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label>{tr('Hint')}</label>
                    <input name="hint" type="text" value={$base->getHint()} disabled={true}/>
                  </div>
                  <div class="col col-1-2 el--block-label el--full-text">
                    <label>{tr('Hint Penalty')}</label>
                    <input name="penalty" type="text" value={strval($base->getPenalty())} disabled={true}/>
                  </div>
                </div>
              </div>
            </div>
          </form>
          {$attachments_div}
          {$links_div}
          <div class="admin-buttons admin-row">
            <div class="button-right">
              <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
              <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
              <button class="fb-cta cta--yellow" data-action="save">{tr('Save')}</button>
            </div>
            <div class="button-left">
              <button class="fb-cta" data-action="add-attachment">{tr('+ Attachment')}</button>
              <button class="fb-cta" data-action="add-link">{tr('+ Link')}</button>
            </div>
          </div>
        </section>
      );
      $c++;
    }

    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Bases Management')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
        <div class="admin-buttons">
          <button class="fb-cta" data-action="add-new">{tr('Add Base Level')}</button>
        </div>
      </div>;
  }

  public async function genRenderCategoriesContent(): Awaitable<:xhp> {
    $adminsections =
      <div class="admin-sections">
      </div>;

    $adminsections->appendChild(
      <section class="admin-box completely-hidden">
        <form class="categories_form">
          <header class="admin-box-header">
            <h3>{tr('New Category')}</h3>
          </header>
          <div class="fb-column-container">
            <div class="col col-pad">
              <div class="form-el el--block-label el--full-text">
                <label class="admin-label" for="">{tr('Category')}: </label>
                <input name="category" type="text" value=""/>
              </div>
            </div>
          </div>
          <div class="admin-buttons admin-row">
            <div class="button-right">
              <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
              <button class="fb-cta cta--yellow" data-action="create">{tr('Create')}</button>
            </div>
          </div>
        </form>
      </section>
    );

    $categories = await Category::genAllCategories();

    foreach ($categories as $category) {
      if ($category->getProtected()) {
        $category_name = <span class="logo-name">{$category->getCategory()}</span>;
      } else {
        $category_name =
          <div>
            <input name="category" type="text" value={$category->getCategory()}/>
            <a class="highlighted--yellow" href="#" data-action="save-category">{tr('Save')}</a>
          </div>;
      }

      $is_used = await Category::genIsUsed($category->getId());;
      if ($is_used || $category->getProtected()) {
        $delete_action = <a></a>;
      } else {
        $delete_action = <a class="highlighted--red" href="#" data-action="delete">{tr('DELETE')}</a>;
      }
      $adminsections->appendChild(
        <section class="admin-box">
          <form class="categories_form">
            <input type="hidden" name="category_id" value={strval($category->getId())}/>
            <header class="management-header">
              <h6>ID{strval($category->getId())}</h6>
              {$delete_action}
            </header>
            <div class="fb-column-container">
              <div class="col col-pad">
                <div class="category">
                  <label>{tr('Category: ')}</label>
                  {$category_name}
                </div>
              </div>
            </div>
          </form>
        </section>
      );
    }

    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Categories Management')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
        <div class="admin-buttons">
          <button class="fb-cta" data-action="add-new">{tr('Add Category')}</button>
        </div>
      </div>;
  }

  public async function genRenderCountriesContent(): Awaitable<:xhp> {
    $adminsections =
      <div class="admin-sections">
      </div>;

    $adminsections->appendChild(
      <section id="new-element" class="admin-box">
        <header class="admin-box-header">
          <h3>{tr('Filter By:')}</h3>
          <div class="form-el fb-column-container col-gutters">
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
              <select class="not_configuration" name="use_filter">
                <option class="filter_option" value="all">{tr('All Countries')}</option>
                <option class="filter_option" value="Yes">{tr('In Use')}</option>
                <option class="filter_option" value="No">{tr('Not Used')}</option>
              </select>
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
              <select class="not_configuration" name="country_status_filter">
                <option class="filter_option" value="all">{tr('All Status')}</option>
                <option class="filter_option" value="enabled">{tr('Enabled')}</option>
                <option class="filter_option" value="disabled">{tr('Disabled')}</option>
              </select>
            </div>
            <div class="col col-1-5 el--block-label el--full-text">
            </div>
          </div>
        </header>
      </section>
    );

    $all_countries = await Country::genAllCountries();
    foreach ($all_countries as $country) {
      $using_country = await Level::genWhoUses($country->getId());
      $current_use = ($using_country) ? tr('Yes') : tr('No');
      if ($country->getEnabled()) {
        $highlighted_action = 'disable_country';
        $highlighted_color = 'highlighted--red country-enabled';
      } else {
        $highlighted_action = 'enable_country';
        $highlighted_color = 'highlighted--green country-disabled';
      }
      $current_status = strtoupper(explode('_', $highlighted_action)[0]);

      if (!$using_country) {
        $status_action =
          <a class={$highlighted_color} href="#" data-action={str_replace('_', '-', $highlighted_action)}>
            {$current_status}
          </a>;
      } else {
        $status_action = <a class={$highlighted_color}></a>;
      }

      $adminsections->appendChild(
        <section class="admin-box">
          <form class="country_form">
            <input type="hidden" name="country_id" value={strval($country->getId())}/>
            <input type="hidden" name="status_action" value={$highlighted_action}/>
            <header class="management-header">
              <h6>ID{strval($country->getId())}</h6>
              {$status_action}
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-2-3">
                <div class="selected-logo">
                  <label>{tr('Country')}: </label>
                  <span class="logo-name">{$country->getName()}</span>
                </div>
              </div>
              <div class="col col-pad col-1-3">
                <div class="selected-logo">
                  <label>{tr('ISO Code')}: </label>
                  <span class="logo-name">{$country->getIsoCode()}</span>
                </div>
                <div class="selected-logo">
                  <label>{tr('In Use')}: </label>
                  <span class="logo-name country-use">{$current_use}</span>
                </div>
              </div>
            </div>
          </form>
        </section>
      );
    }
    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Countries Management')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
      </div>;
  }

  private async function genGenerateTeamNames(int $team_id): Awaitable<:xhp> {
    $names = <section class="admin-box"></section>;

    $teams_data = await Team::genTeamData($team_id);

    if (count($teams_data) > 0) {
      foreach ($teams_data as $data) {
        $names->appendChild(
          <div class="fb-column-container">
            <div class="col col-pad col-2-3">
              <div class="form-el el--block-label el--full-text">
                <label class="admin-label" for="">{tr('Name')}</label>
                <input name="name" type="text" value={$data['name']} disabled={true}/>
              </div>
            </div>
            <div class="col col-pad col-2-3">
              <div class="form-el el--block-label el--full-text">
                <label class="admin-label" for="">{tr('Email')}</label>
                <input name="email" type="text" value={$data['email']} disabled={true}/>
              </div>
            </div>
          </div>
        );
      }
    } else {
      $names->appendChild(
        <div class="fb-column-container">
          <div class="col col-pad">
            {tr('No Team Names')}
          </div>
        </div>
      );
    }

    return $names;
  }

  private async function genGenerateTeamScores(
    int $team_id,
  ): Awaitable<:xhp> {
    $scores_div = <div></div>;
    $scores = await ScoreLog::genAllScoresByTeam($team_id);
    if (count($scores) > 0) {
      $scores_tbody = <tbody></tbody>;
      foreach ($scores as $score) {
        $level = await Level::gen($score->getLevelId());
        $country = await Country::gen($level->getEntityId());
        $level_str = $country->getName() . ' - ' . $level->getTitle();
        $scores_tbody->appendChild(
          <tr>
            <td style="width: 20%;">{time_ago($score->getTs())}</td>
            <td style="width: 13%;">{$score->getType()}</td>
            <td style="width: 7%;">{strval($score->getPoints())}</td>
            <td style="width: 60%;">{$level_str}</td>
          </tr>
        );
      }
      $scores_div->appendChild(
        <table>
          <thead>
            <tr>
              <th style="width: 20%;">{tr('time')}_</th>
              <th style="width: 13%;">{tr('type')}_</th>
              <th style="width: 7%;">{tr('pts')}_</th>
              <th style="width: 60%;">{tr('Level')}_</th>
            </tr>
          </thead>
          {$scores_tbody}
        </table>
      );
    } else {
      $scores_div->appendChild(
        <div class="fb-column-container">
          <div class="col col-pad">
            {tr('No Scores')}
          </div>
        </div>
      );
    }

    return $scores_div;
  }

  private async function genGenerateTeamFailures(int $team_id): Awaitable<:xhp> {
    $failures_div = <div></div>;
    $failures = await FailureLog::genAllFailuresByTeam($team_id);
    if (count($failures) > 0) {
      $failures_tbody = <tbody></tbody>;
      foreach ($failures as $failure) {
        $level = await Level::gen($failure->getLevelId());
        $country = await Country::gen($level->getEntityId());
        $level_str = $country->getName() . ' - ' . $level->getTitle();
        $failures_tbody->appendChild(
          <tr>
            <td style="width: 20%;">{time_ago($failure->getTs())}</td>
            <td style="width: 40%;">{$level_str}</td>
            <td style="width: 40%;">{$failure->getFlag()}</td>
          </tr>
        );
      }
      $failures_div->appendChild(
        <table>
          <thead>
            <tr>
              <th style="width: 20%;">{tr('time')}_</th>
              <th style="width: 40%;">{tr('Level')}_</th>
              <th style="width: 40%;">{tr('Attempt')}_</th>
            </tr>
          </thead>
          {$failures_tbody}
        </table>
      );
    } else {
      $failures_div->appendChild(
        <div class="fb-column-container">
          <div class="col col-pad">
            {tr('No Failures')}
          </div>
        </div>
      );
    }

    return $failures_div;
  }

  private async function genGenerateTeamTabs(int $team_id): Awaitable<:xhp> {
    $team_tabs_team = 'fb--teams--tabs--team-team'.strval($team_id);
    $team_tabs_names = 'fb--teams--tabs--names-team'.strval($team_id);
    $team_tabs_scores = 'fb--teams--tabs--scores-team'.strval($team_id);
    $team_tabs_failures = 'fb--teams--tabs--failures-team'.strval($team_id);
    $team_tabs_name = 'fb--teams--tabs-team'.strval($team_id);
    $tab_team = 'team'.strval($team_id);
    $tab_names = 'names'.strval($team_id);
    $tab_scores = 'scores'.strval($team_id);
    $tab_failures = 'failures'.strval($team_id);

    $team_tabs = <div class="radio-tabs"></div>;
    $team_tabs->appendChild(
      <input type="radio" value={$tab_team} name={$team_tabs_name} id={$team_tabs_team} checked={true}/>
    );
    $team_tabs->appendChild(
      <label for={$team_tabs_team}>{tr('Team')}</label>
    );

    $registration_names = await Configuration::gen('registration_names');
    if ($registration_names->getValue() === '1') {
      $team_tabs->appendChild(
        <input type="radio" value={$tab_names} name={$team_tabs_name} id={$team_tabs_names}/>
      );
      $team_tabs->appendChild(
        <label for={$team_tabs_names}>{tr('Names')}</label>
      );
    }

    $team_tabs->appendChild(
      <input type="radio" value={$tab_scores} name={$team_tabs_name} id={$team_tabs_scores}/>
    );
    $team_tabs->appendChild(
      <label for={$team_tabs_scores}>{tr('Scores')}</label>
    );

    $team_tabs->appendChild(
      <input type="radio" value={$tab_failures} name={$team_tabs_name} id={$team_tabs_failures}/>
    );
    $team_tabs->appendChild(
      <label for={$team_tabs_failures}>{tr('Failures')}</label>
    );

    return $team_tabs;
  }

  public async function genRenderTeamsContent(): Awaitable<:xhp> {
    $adminsections =
      <div class="admin-sections">
        <section class="admin-box validate-form section-locked completely-hidden">
          <form class="team_form">
            <header class="admin-box-header">
              <h3>{tr('New Team')}</h3>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-1-2">
                <div class="form-el--required el--block-label el--full-text">
                  <label class="admin-label" for="">{tr('Team Name')}</label>
                  <input name="team_name" type="text" value="" maxlength={20}/>
                </div>
              </div>
              <div class="col col-pad col-1-2">
                <div class="form-el--required el--block-label el--full-text">
                  <label class="admin-label" for="">{tr('Password')}</label>
                  <input name="password" type="password" value=""/>
                </div>
              </div>
            </div>
            <div class="admin-row el--block-label">
              <label>{tr('Team Logo')}</label>
              <div class="fb-column-container">
                <div class="col col-shrink">
                  <div class="post-avatar has-avatar">
                    <svg class="icon icon--badge">
                      <use href="#icon--badge-"/>

                    </svg>
                  </div>
                </div>
                <div class="form-el--required col col-grow">
                  <div class="selected-logo">
                    <label>{tr('Selected Logo:')} </label>
                    <span class="logo-name"></span>
                  </div>
                  <a href="#" class="alt-link js-choose-logo">{tr('Select Logo')} </a>
                </div>
                <div class="col col-shrink admin-buttons">
                  <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
                  <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
                  <button class="fb-cta cta--yellow js-confirm-save" data-action="create">{tr('Create')}</button>
                </div>
              </div>
            </div>
          </form>
        </section>
        <section class="admin-box">
          <header class="admin-box-header">
            <h3>{tr('All Teams')}</h3>
            <form class="all_team_form">
              <div class="admin-section-toggle radio-inline col">
                <input type="radio" name="fb--teams--all_team" id="fb--teams--all_team--on"/>
                <label for="fb--teams--all_team--on">{tr('On')}</label>
                <input type="radio" name="fb--teams--all_team" id="fb--teams--all_team--off"/>
                <label for="fb--teams--all_team--off">{tr('Off')}</label>
              </div>
            </form>
          </header>
        </section>
      </div>;

    $c = 1;
    $all_teams = await Team::genAllTeams();
    foreach ($all_teams as $team) {
      $xlink_href = '#icon--badge-'.$team->getLogo();
      $team_protected = $team->getProtected();
      $team_active_on = $team->getActive();
      $team_active_off = !$team->getActive();
      $team_admin_on = $team->getAdmin();
      $team_admin_off = !$team->getAdmin();
      $team_visible_on = $team->getVisible();
      $team_visible_off = !$team->getVisible();

      $team_status_name = 'fb--teams--team-'.strval($team->getId()).'-status';
      $team_status_on_id = 'fb--teams--team-'.strval($team->getId()).'-status--on';
      $team_status_off_id = 'fb--teams--team-'.strval($team->getId()).'-status--off';
      $team_admin_name = 'fb--teams--team-'.strval($team->getId()).'-admin';
      $team_admin_on_id = 'fb--teams--team-'.strval($team->getId()).'-admin--on';
      $team_admin_off_id = 'fb--teams--team-'.strval($team->getId()).'-admin--off';
      $team_visible_name = 'fb--teams--team-'.strval($team->getId()).'-visible';
      $team_visible_on_id = 'fb--teams--team-'.strval($team->getId()).'-visible--on';
      $team_visible_off_id = 'fb--teams--team-'.strval($team->getId()).'-visible--off';

      if ($team_protected) {
        $toggle_status =
          <div class="admin-section-toggle radio-inline">
            <input type="radio" name={$team_status_name} id={$team_status_on_id} checked={$team_active_on}/>
            <label for={$team_status_on_id}>{tr('On')}</label>
          </div>;
        $toggle_admin =
          <div class="admin-section-toggle radio-inline">
            <input type="radio" name={$team_admin_name} id={$team_admin_on_id} checked={$team_admin_on}/>
            <label for={$team_admin_on_id}>{tr('On')}</label>
          </div>;
        $delete_button = <button class="fb-cta cta--red" disabled={true}>{tr('Protected')}</button>;
      } else {
        $toggle_status =
          <div class="admin-section-toggle radio-inline">
            <input type="radio" name={$team_status_name} id={$team_status_on_id} checked={$team_active_on}/>
            <label for={$team_status_on_id}>{tr('On')}</label>
            <input type="radio" name={$team_status_name} id={$team_status_off_id} checked={$team_active_off}/>
            <label for={$team_status_off_id}>{tr('Off')}</label>
          </div>;
        $toggle_admin =
          <div class="admin-section-toggle radio-inline">
            <input type="radio" name={$team_admin_name} id={$team_admin_on_id} checked={$team_admin_on}/>
            <label for={$team_admin_on_id}>{tr('On')}</label>
            <input type="radio" name={$team_admin_name} id={$team_admin_off_id} checked={$team_admin_off}/>
            <label for={$team_admin_off_id}>{tr('Off')}</label>
          </div>;
        $delete_button = <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>;
      }

      $tab_team = 'team'.strval($team->getId());
      $tab_names = 'names'.strval($team->getId());
      $tab_scores = 'scores'.strval($team->getId());
      $tab_failures = 'failures'.strval($team->getId());

      $team_tabs = await $this->genGenerateTeamTabs($team->getId());
      $team_names = await $this->genGenerateTeamNames($team->getId());
      $team_scores = await $this->genGenerateTeamScores($team->getId());
      $team_failures = await $this->genGenerateTeamFailures($team->getId());

      $adminsections->appendChild(
        <div>
          {$team_tabs}
          <div class="tab-content-container">
            <div class="radio-tab-content active" data-tab={$tab_team}>
              <section class="admin-box validate-form section-locked">
                <form class="team_form" name={strval($team->getId())}>
                  <input type="hidden" name="team_id" value={strval($team->getId())}/>
                  <header class="admin-box-header">
                    <h3>{tr('Team')} {$c}</h3>
                    {$toggle_status}
                  </header>
                  <div class="fb-column-container">
                    <div class="col col-pad col-1-3">
                      <div class="form-el form-el--required el--block-label el--full-text">
                        <label class="admin-label" for="">{tr('Team Name')}</label>
                        <input name="team_name" type="text" value={$team->getName()} maxlength={20} disabled={true}/>
                      </div>
                      <div class="form-el form-el--required el--block-label el--full-text">
                        <label class="admin-label" for="">{tr('Score')}</label>
                        <input name="points" type="text" value={strval($team->getPoints())} disabled={true}/>
                      </div>
                    </div>
                    <div class="col col-pad col-1-3">
                      <div class="form-el el--block-label el--full-text">
                        <label class="admin-label" for="">{tr('Change Password')}</label>
                        <input name="password" type="password" disabled={true}/>
                      </div>
                    </div>
                    <div class="col col-pad col-1-3">
                      <div class="form-el el--block-label">
                        <label class="admin-label" for="">{tr('Admin Level')}</label>
                        {$toggle_admin}
                      </div>
                      <div class="form-el el--block-label">
                        <label class="admin-label" for="">{tr('Visibility')} </label>
                        <div class="admin-section-toggle radio-inline">
                          <input type="radio" name={$team_visible_name} id={$team_visible_on_id} checked={$team_visible_on}/>
                          <label for={$team_visible_on_id}>{tr('On')}</label>
                          <input type="radio" name={$team_visible_name} id={$team_visible_off_id} checked={$team_visible_off}/>
                          <label for={$team_visible_off_id}>{tr('Off')}</label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="admin-row el--block-label">
                    <label>{tr('Team Logo')}</label>
                    <div class="fb-column-container">
                      <div class="col col-shrink">
                        <div class="post-avatar has-avatar">
                          <svg class="icon icon--badge">
                            <use href={$xlink_href} />

                          </svg>
                        </div>
                      </div>
                      <div class="form-el--required col col-grow">
                        <div class="selected-logo">
                          <label>{tr('Selected Logo:')} </label>
                          <span class="logo-name">{$team->getLogo()}</span>
                        </div>
                        <a href="#" class="alt-link js-choose-logo">{tr('Select Logo')} </a>
                      </div>
                      <div class="col col-shrink admin-buttons">
                        <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
                        {$delete_button}
                        <button class="fb-cta cta--yellow js-confirm-save" data-action="save">{tr('Save')}</button>
                      </div>
                    </div>
                  </div>
                </form>
              </section>
            </div>
            <div class="radio-tab-content" data-tab={$tab_names}>
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Team')} {$c}</h3>
                </header>
                {$team_names}
              </section>
            </div>
            <div class="radio-tab-content" data-tab={$tab_scores}>
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Team')} {$c}</h3>
                </header>
                {$team_scores}
              </section>
            </div>
            <div class="radio-tab-content" data-tab={$tab_failures}>
              <section class="admin-box">
                <header class="admin-box-header">
                  <h3>{tr('Team')} {$c}</h3>
                </header>
                {$team_failures}
              </section>
            </div>
          </div>
        </div>
      );
      $c++;
    }
    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Team Management')}</h3>
          <span class="admin-section--status">status_<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
        <div class="admin-buttons">
          <button class="fb-cta" data-action="add-new">{tr('Add Team')}</button>
        </div>
      </div>;
  }

  public async function genRenderLogosContent(): Awaitable<:xhp> {
    $adminsections =
      <div class="admin-sections">
      </div>;

    $all_logos = await Logo::genAllLogos();
    foreach ($all_logos as $logo) {
      $xlink_href = '#icon--badge-'.$logo->getName();
      $using_logo = await Team::genWhoUses($logo->getName());
      $current_use = (count($using_logo) > 0) ? tr('Yes') : tr('No');
      if ($logo->getEnabled()) {
        $highlighted_action = 'disable_logo';
        $highlighted_color = 'highlighted--red';
      } else {
        $highlighted_action = 'enable_logo';
        $highlighted_color = 'highlighted--green';
      }
      $action_text = strtoupper(explode('_', $highlighted_action)[0]);

      if ($using_logo) {
        $use_select = <select class="not_configuration"></select>;
        foreach ($using_logo as $t) {
          $use_select->appendChild(<option value="">{$t->getName()}</option>);
        }
      } else {
        $use_select = <select class="not_configuration"><option value="0">{tr('None')}</option></select>;
      }

      $adminsections->appendChild(
        <section class="admin-box">
          <form class="logo_form">
            <input type="hidden" name="logo_id" value={strval($logo->getId())}/>
            <input type="hidden" name="status_action" value={strtolower($action_text)}/>
            <header class="management-header">
              <h6>ID{strval($logo->getId())}</h6>
              <a class={$highlighted_color} href="#" data-action={str_replace('_', '-', $highlighted_action)}>{$action_text}</a>
            </header>
            <div class="fb-column-container">
              <div class="col col-pad col-shrink">
                <div class="post-avatar has-avatar">
                  <svg class="icon icon--badge">
                    <use href={$xlink_href}></use>

                  </svg>
                </div>
              </div>
              <div class="col col-pad col-grow">
                <div class="selected-logo">
                  <label>{tr('Logo Name')}: </label>
                  <span class="logo-name">{$logo->getName()}</span>
                </div>
                <div class="selected-logo">
                  <label>{tr('In use')}: </label>
                  <span class="logo-name">{$current_use}</span>
                </div>
              </div>
              <div class="col col-pad col-1-3">
                <div class="form-el el--select el--block-label">
                  <label for="">{tr('Used By')}:</label>
                  {$use_select}
                </div>
              </div>
            </div>
          </form>
        </section>
      );
    }

    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Logo Management')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
      </div>;
  }

  public async function genRenderSessionsContent(): Awaitable<:xhp> {
    $adminsections =
      <div class="admin-sections">
      </div>;

    $c = 1;
    $all_sessions = await Session::genAllSessions();
    foreach ($all_sessions as $session) {
      $session_id = 'session_'.strval($session->getId());
      $team = await Team::genTeam($session->getTeamId());
      $adminsections->appendChild(
        <section class="admin-box section-locked">
          <form class="session_form" name={$session_id}>
            <input type="hidden" name="session_id" value={strval($session->getId())}/>
            <header class="admin-box-header">
              <span class="session-name">{tr('Session')} {$c}: <span class="highlighted--blue">{$team->getName()}</span></span>
            </header>
            <div class="fb-column-container">
              <div class="col col-1-3 col-pad">
                <div class="form-el el--block-label el--full-text">
                  <label class="admin-label">{tr('Cookie')}</label>
                  <input name="cookie" type="text" value={$session->getCookie()} disabled={true}/>
                </div>
              </div>
              <div class="col col-1-3 col-pad">
                <div class="form-el el--block-label el--full-text">
                  <label class="admin-label">{tr('Creation Time')}:</label>
                  <span class="highlighted">
                    <label class="admin-label">{time_ago($session->getCreatedTs())}</label>
                  </span>
                </div>
              </div>
              <div class="col col-1-3 col-pad">
                <div class="form-el el--block-label el--full-text">
                  <label class="admin-label">{tr('Last Access')}:</label>
                  <span class="highlighted">
                    <label class="admin-label">{time_ago($session->getLastAccessTs())}</label>
                  </span>
                </div>
              </div>
            </div>
            <div class="admin-row">
              <div class="form-el el--block-label el--full-text">
                <label class="admin-label">{tr('Data')}</label>
                <input name="data" type="text" value={$session->getData()} disabled={true}/>
              </div>
            </div>
            <div class="admin-buttons admin-row">
              <div class="button-right">
                <a href="#" class="admin--edit" data-action="edit">{tr('EDIT')}</a>
                <button class="fb-cta cta--red" data-action="delete">{tr('Delete')}</button>
              </div>
            </div>
          </form>
        </section>
      );
      $c++;
    }
    return
      <div>
        <header class="admin-page-header">
          <h3>{tr('Sessions')}</h3>
          <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
        </header>
        {$adminsections}
      </div>;
  }

  public async function genRenderLogsContent(): Awaitable<:xhp> {
    $gamelogs = await GameLog::genGameLog();

    if (count($gamelogs) > 0) {
      $logs_tbody = <tbody></tbody>;
      foreach ($gamelogs as $gamelog) {
        if ($gamelog->getEntry() === 'score') {
          $log_entry = <span class="highlighted--green">{$gamelog->getEntry()}</span>;
        } else {
          $log_entry = <span class="highlighted--red">{$gamelog->getEntry()}</span>;
        }
        $team = await Team::genTeam($gamelog->getTeamId());
        $level = await Level::gen($gamelog->getLevelId());
        $country = await Country::gen($level->getEntityId());
        $level_str = $country->getName() . ' - ' . $level->getTitle() . ' - ' . $level->getType();
        $logs_tbody->appendChild(
          <tr>
            <td>{time_ago($gamelog->getTs())}</td>
            <td>{$log_entry}</td>
            <td>{$level_str}</td>
            <td>{strval($gamelog->getPoints())}</td>
            <td>{$team->getName()}</td>
            <td>{$gamelog->getFlag()}</td>
          </tr>
        );
      }
      $logs_table =
        <table>
          <thead>
              <tr>
                <th>{tr('time')}_</th>
                <th>{tr('entry')}_</th>
                <th>{tr('level')}_</th>
                <th>{tr('pts')}_</th>
                <th>{tr('team')}_</th>
                <th>{tr('flag')}_</th>
              </tr>
            </thead>
            {$logs_tbody}
        </table>;
    } else {
      $logs_table =
        <div class="fb-column-container">
          <div class="col col-pad">
            {tr('No Entries')}
          </div>
        </div>;
    }

    return
      <div>
      <header class="admin-page-header">
        <h3>{tr('Game Logs')}</h3>
        <span class="admin-section--status">{tr('status_')}<span class="highlighted">{tr('OK')}</span></span>
      </header>
      <div class="admin-sections">
        <section class="admin-box">
          <header class="admin-box-header">
            <h3>{tr('Game Logs Timeline')}</h3>
          </header>
          <div class="fb-column-container">
            {$logs_table}
          </div>
        </section>
      </div>
    </div>;
  }

  public function renderMainContent(): :xhp {
    return
      <h1>{tr('ADMIN')}</h1>;
  }

  public async function genRenderMainNav(): Awaitable<:xhp> {
    $game = await Configuration::gen('game');
    $game_status = $game->getValue() === '1';
    if ($game_status) {
      $game_action =
        <a href="#" class="fb-cta cta--red js-end-game">
          {tr('End Game')}
        </a>;
    } else {
      $game_action =
        <a href="#" class="fb-cta cta--yellow js-begin-game">
          {tr('Begin Game')}
        </a>;
    }
    return
      <div id="fb-admin-nav" class="admin-nav-bar fb-row-container">
        <header class="admin-nav-header row-fixed">
          <h2>{tr('Game Admin')}</h2>
        </header>
        <nav class="admin-nav-links row-fluid">
          <ul>
            <li><a href="/index.php?p=admin&page=configuration">{tr('Configuration')}</a></li>
            <!--<li><a href="/index.php?p=admin&page=controls">{tr('Controls')}</a></li>-->
            <li><a href="/index.php?p=admin&page=announcements">{tr('Announcements')}</a></li>
            <li><a href="/index.php?p=admin&page=quiz">{tr('Levels')}: {tr('Quiz')}</a></li>
            <li><a href="/index.php?p=admin&page=flags">{tr('Levels')}: {tr('Flags')}</a></li>
            <li><a href="/index.php?p=admin&page=bases">{tr('Levels')}: {tr('Bases')}</a></li>
            <li><a href="/index.php?p=admin&page=categories">{tr('Levels')}: {tr('Categories')}</a></li>
            <li><a href="/index.php?p=admin&page=countries">{tr('Levels')}: {tr('Countries')}</a></li>
            <li><a href="/index.php?p=admin&page=teams">{tr('Teams')}</a></li>
            <li><a href="/index.php?p=admin&page=logos">{tr('Teams')}: {tr('Logos')}</a></li>
            <li><a href="/index.php?p=admin&page=sessions">{tr('Teams')}: {tr('Sessions')}</a></li>
            <li><a href="/index.php?p=admin&page=logs">{tr('Game Logs')}</a></li>
          </ul>
          {$game_action}
        </nav>
        <div class="admin-nav--footer row-fixed">
          <a href="/index.php?p=game">{tr('Gameboard')}</a>
          <a href="" class="js-prompt-logout">{tr('Logout')}</a>
          <a></a>
          <fbbranding />
        </div>
      </div>;
  }

  public async function genRenderPage(string $page): Awaitable<:xhp> {
    switch ($page) {
      case 'main':
        // Render the configuration page by default
        return await $this->genRenderConfigurationContent();
        break;
      case 'configuration':
        return await $this->genRenderConfigurationContent();
        break;
      case 'controls':
        return $this->renderControlsContent();
        break;
      case 'announcements':
        return await $this->genRenderAnnouncementsContent();
        break;
      case 'quiz':
        return await $this->genRenderQuizContent();
        break;
      case 'flags':
        return await $this->genRenderFlagsContent();
        break;
      case 'bases':
        return await $this->genRenderBasesContent();
        break;
      case 'categories':
        return await $this->genRenderCategoriesContent();
        break;
      case 'countries':
        return await $this->genRenderCountriesContent();
        break;
      case 'teams':
        return await $this->genRenderTeamsContent();
        break;
      case 'logos':
        return await $this->genRenderLogosContent();
        break;
      case 'sessions':
        return await $this->genRenderSessionsContent();
        break;
      case 'logs':
        return await $this->genRenderLogsContent();
        break;
      default:
        return $this->renderMainContent();
        break;
    }
  }

  <<__Override>>
  public async function genRenderBody(string $page): Awaitable<:xhp> {
    $rendered_page = await $this->genRenderPage($page);
    $rendered_main_nav = await $this->genRenderMainNav();
    return
      <body data-section="admin">
        <input type="hidden" name="csrf_token" value={SessionUtils::CSRFToken()}/>
        <div style="height: 0; width: 0; position: absolute; visibility: hidden" id="fb-svg-sprite"></div>
        <div class="fb-viewport admin-viewport">
          {$rendered_main_nav}
          <div id="fb-main-content" class="fb-page fb-admin-main">{$rendered_page}</div>
        </div>
        <script type="text/javascript" src="static/dist/js/app.js"></script>
      </body>;
  }
}

import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';

export default class ChatBotSettings extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);
    this.loading = false;
  }

  content() {
    return (
      <div className="ExtensionPage-settings">
        <div className="container">
          <div className="Form">
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.server_url',
              type: 'text',
              label: app.translator.trans('ccdc-chatbot.admin.settings.server_url'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.server_url_help', {
                a: <a href="http://localhost:11434" target="_blank" rel="noopener" />,
              }),
              placeholder: 'http://localhost:11434',
            })}
            {this.buildSettingComponent({
                setting: 'ccdc-chatbot.api_key',
                type: 'text',
                label: app.translator.trans('ccdc-chatbot.admin.settings.api_key'),
                help: app.translator.trans('ccdc-chatbot.admin.settings.api_key_help', {
                  a: <a href="http://localhost:11434" target="_blank" rel="noopener" />,
                }),
                placeholder: "ak",
              })
            }
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.model',
              type: 'dropdown',
              options: {
                'deepseek-chat': 'deepseek-chat',
                'deepseek-coder': 'deepseek-coder'
              },
              label: app.translator.trans('ccdc-chatbot.admin.settings.model_label'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.model_help', {
                a: <a href="http://localhost:11434/models" target="_blank" rel="noopener" />,
              }),
            })}
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.elasticsearch_url',
              type: 'text',
              label: app.translator.trans('ccdc-chatbot.admin.settings.elasticsearch_url'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.elasticsearch_url_help', {
                a: <a href="http://localhost:9200" target="_blank" rel="noopener" />,
              }),
              placeholder: 'http://localhost:9200',
            })}
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.elasticsearch_api_key',
              type: 'text',
              label: app.translator.trans('ccdc-chatbot.admin.settings.elasticsearch_api_key'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.elasticsearch_api_key_help', {
                a: <a href="http://localhost:9200" target="_blank" rel="noopener" />,
              }),
              placeholder: "abc123",
            })}
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.max_tokens',
              type: 'number',
              label: app.translator.trans('ccdc-chatbot.admin.settings.max_tokens_label'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.max_tokens_help', {
                a: <a href="https://help.openai.com/en/articles/4936856" target="_blank" rel="noopener" />,
              }),
              default: 100,
            })}
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.user_prompt',
              type: 'text',
              label: app.translator.trans('ccdc-chatbot.admin.settings.user_prompt_label'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.user_prompt_help'),
            })}
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.user_prompt_badge_text',
              type: 'text',
              label: app.translator.trans('ccdc-chatbot.admin.settings.user_prompt_badge_label'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.user_prompt_badge_help'),
            })}
            {this.buildSettingComponent({
              setting: 'ccdc-chatbot.enable_on_discussion_started',
              type: 'boolean',
              label: app.translator.trans('ccdc-chatbot.admin.settings.enable_on_discussion_started_label'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.enable_on_discussion_started_help'),
            })}
            {this.buildSettingComponent({
              type: 'flarum-tags.select-tags',
              setting: 'ccdc-chatbot.enabled-tags',
              label: app.translator.trans('ccdc-chatbot.admin.settings.enabled_tags_label'),
              help: app.translator.trans('ccdc-chatbot.admin.settings.enabled_tags_help'),
              options: {
                requireParentTag: false,
                limits: {
                  max: {
                    secondary: 0,
                  },
                },
              },
            })}
            <div className="Form-group">{this.submitButton()}</div>
          </div>
        </div>
      </div>
    );
  }
}

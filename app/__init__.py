from flask import Flask
from flask_wtf import CSRFProtect
from .config import Config

csrf = CSRFProtect()


def create_app() -> Flask:
    app = Flask(__name__)
    app.config.from_object(Config)

    csrf.init_app(app)

    from .views import bp as main_bp
    app.register_blueprint(main_bp)

    return app

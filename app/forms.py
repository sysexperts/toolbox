from flask_wtf import FlaskForm
from wtforms import PasswordField, StringField, SubmitField
from wtforms.validators import DataRequired, Length


class LoginForm(FlaskForm):
    username = StringField(
        "Benutzername",
        validators=[DataRequired(message="Bitte Benutzername eingeben."), Length(max=64)],
    )
    password = PasswordField(
        "Passwort",
        validators=[DataRequired(message="Bitte Passwort eingeben."), Length(max=128)],
    )
    submit = SubmitField("Anmelden")

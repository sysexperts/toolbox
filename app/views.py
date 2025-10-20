from flask import Blueprint, flash, redirect, render_template, session, url_for

from .forms import LoginForm

bp = Blueprint("main", __name__)

ADMIN_CREDENTIALS = {
    "username": "admin",
    "password": "admin",
}


@bp.route("/", methods=["GET", "POST"])
@bp.route("/login", methods=["GET", "POST"])
def login():
    form = LoginForm()

    if form.validate_on_submit():
        username = form.username.data.strip()
        password = form.password.data

        if username == ADMIN_CREDENTIALS["username"] and password == ADMIN_CREDENTIALS["password"]:
            session.clear()
            session["user"] = username
            flash("Erfolgreich angemeldet.", "success")
            return redirect(url_for("main.admin"))

        flash("Ungültige Zugangsdaten.", "error")

    privacy_url = url_for("main.privacy")
    return render_template("login.html", form=form, privacy_url=privacy_url)


@bp.route("/admin")
def admin():
    user = session.get("user")
    if user != ADMIN_CREDENTIALS["username"]:
        flash("Bitte melden Sie sich an, um fortzufahren.", "error")
        return redirect(url_for("main.login"))

    return render_template("admin.html", user=user)


@bp.route("/logout")
def logout():
    session.clear()
    flash("Sie wurden abgemeldet.", "info")
    return redirect(url_for("main.login"))


@bp.route("/datenschutz")
def privacy():
    return render_template("privacy.html")

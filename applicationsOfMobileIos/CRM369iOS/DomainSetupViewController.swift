import UIKit

final class DomainSetupViewController: UIViewController, UITextFieldDelegate {
    private let onSubmit: (URL) -> Void
    private let domainTextField = UITextField()
    private let errorLabel = UILabel()

    init(savedValue: String, onSubmit: @escaping (URL) -> Void) {
        self.onSubmit = onSubmit
        super.init(nibName: nil, bundle: nil)
        domainTextField.text = savedValue
    }

    @available(*, unavailable)
    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = UIColor(named: "LaunchBackground") ?? .systemGroupedBackground

        let logoView = UIImageView(image: UIImage(named: "Logo"))
        logoView.contentMode = .scaleAspectFit

        let brandLabel = UILabel()
        brandLabel.text = "CRM369"
        brandLabel.font = .systemFont(ofSize: 16, weight: .semibold)
        brandLabel.textColor = UIColor(red: 10 / 255, green: 108 / 255, blue: 116 / 255, alpha: 1)
        brandLabel.textAlignment = .center

        let titleLabel = UILabel()
        titleLabel.text = "Подключение к сайту"
        titleLabel.font = .systemFont(ofSize: 28, weight: .bold)
        titleLabel.textAlignment = .center

        let descriptionLabel = UILabel()
        descriptionLabel.text = "Укажите домен CRM. После подключения откроется полная мобильная версия с вашей обычной авторизацией."
        descriptionLabel.font = .preferredFont(forTextStyle: .body)
        descriptionLabel.textColor = .secondaryLabel
        descriptionLabel.textAlignment = .center
        descriptionLabel.numberOfLines = 0

        domainTextField.placeholder = "crm.company.com"
        domainTextField.borderStyle = .roundedRect
        domainTextField.keyboardType = .URL
        domainTextField.textContentType = .URL
        domainTextField.autocapitalizationType = .none
        domainTextField.autocorrectionType = .no
        domainTextField.returnKeyType = .go
        domainTextField.delegate = self
        domainTextField.accessibilityIdentifier = "domainTextField"

        errorLabel.font = .preferredFont(forTextStyle: .footnote)
        errorLabel.textColor = .systemRed
        errorLabel.numberOfLines = 0
        errorLabel.isHidden = true

        var continueConfiguration = UIButton.Configuration.filled()
        continueConfiguration.title = "Продолжить"
        continueConfiguration.cornerStyle = .large
        continueConfiguration.baseBackgroundColor = UIColor(red: 10 / 255, green: 108 / 255, blue: 116 / 255, alpha: 1)
        let continueButton = UIButton(configuration: continueConfiguration)
        continueButton.addTarget(self, action: #selector(submitDomain), for: .touchUpInside)
        continueButton.accessibilityIdentifier = "continueButton"

        let stackView = UIStackView(arrangedSubviews: [
            logoView,
            brandLabel,
            titleLabel,
            descriptionLabel,
            domainTextField,
            errorLabel,
            continueButton,
        ])
        stackView.axis = .vertical
        stackView.spacing = 16
        stackView.setCustomSpacing(4, after: logoView)
        stackView.setCustomSpacing(28, after: descriptionLabel)
        stackView.translatesAutoresizingMaskIntoConstraints = false

        view.addSubview(stackView)

        NSLayoutConstraint.activate([
            logoView.heightAnchor.constraint(equalToConstant: 92),
            continueButton.heightAnchor.constraint(greaterThanOrEqualToConstant: 50),
            stackView.leadingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.leadingAnchor, constant: 24),
            stackView.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor, constant: -24),
            stackView.centerYAnchor.constraint(equalTo: view.safeAreaLayoutGuide.centerYAnchor),
        ])
    }

    func textFieldShouldReturn(_ textField: UITextField) -> Bool {
        submitDomain()
        return true
    }

    @objc private func submitDomain() {
        guard let baseURL = DomainURLNormalizer.normalize(domainTextField.text ?? "") else {
            errorLabel.text = "Введите корректный домен или URL сайта."
            errorLabel.isHidden = false
            return
        }

        errorLabel.isHidden = true
        view.endEditing(true)
        onSubmit(baseURL)
    }
}

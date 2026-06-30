const lowercaseCharacters = 'abcdefghjkmnpqrstuvwxyz';
const uppercaseCharacters = 'ABCDEFGHJKMNPQRSTUVWXYZ';
const digitCharacters = '23456789';
const symbolCharacters = '!@#$%^&*-_=+?';

const allCharacters =
    lowercaseCharacters +
    uppercaseCharacters +
    digitCharacters +
    symbolCharacters;

const randomUint32 = (): number => {
    if (typeof globalThis.crypto !== 'undefined') {
        const values = new Uint32Array(1);

        globalThis.crypto.getRandomValues(values);

        return values[0] ?? 0;
    }

    return Math.floor(Math.random() * 4294967296);
};

const randomCharacter = (characters: string): string => {
    return characters[randomUint32() % characters.length] ?? characters[0] ?? '';
};

const shuffle = (characters: string[]): string[] => {
    const result = [...characters];

    for (let index = result.length - 1; index > 0; index -= 1) {
        const swapIndex = randomUint32() % (index + 1);
        const current = result[index];

        result[index] = result[swapIndex] ?? current;
        result[swapIndex] = current;
    }

    return result;
};

export type UsePasswordGeneratorReturn = {
    generatePassword: (length?: number) => string;
};

export function usePasswordGenerator(): UsePasswordGeneratorReturn {
    const generatePassword = (length = 16): string => {
        const resolvedLength = Math.max(length, 12);
        const passwordCharacters = [
            randomCharacter(lowercaseCharacters),
            randomCharacter(uppercaseCharacters),
            randomCharacter(digitCharacters),
            randomCharacter(symbolCharacters),
        ];

        while (passwordCharacters.length < resolvedLength) {
            passwordCharacters.push(randomCharacter(allCharacters));
        }

        return shuffle(passwordCharacters).join('');
    };

    return {
        generatePassword,
    };
}
